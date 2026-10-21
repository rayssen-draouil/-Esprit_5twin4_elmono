<?php

namespace App\Http\Controllers\Back;

use App\Exceptions\GroqAiException;
use App\Http\Controllers\Controller;
use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\MaintenanceIntelligenceScan;
use App\Models\User;
use App\Models\Zone;
use App\Services\AiMaintenancePlannerService;
use App\Services\MaintenanceRiskAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceIntelligenceController extends Controller
{
    public function __construct(
        protected MaintenanceRiskAnalysisService $riskAnalysisService,
        protected AiMaintenancePlannerService $plannerService
    ) {}

    /**
     * Display the AI Maintenance Intelligence Center interface.
     */
    public function index(Request $request): View
    {
        // 1. Fetch the latest persisted scan if available
        $latestScan = MaintenanceIntelligenceScan::with('user')->latest()->first();

        // 2. Real-time database metrics for live overview
        $totalInfrastructures = Infrastructure::count();
        $totalMaintenances = Maintenance::count();
        $overdueCount = Maintenance::status('overdue')->count();
        $dueSoonCount = Infrastructure::maintenanceDue()->count();
        $criticalInfrasCount = Infrastructure::whereIn('status', ['critical', 'offline', 'out_of_service'])
            ->orWhereIn('condition', ['poor', 'critical'])
            ->count();

        // 3. Filter options
        $zones = Zone::orderBy('name')->get(['id', 'name']);
        $infrastructureTypes = Infrastructure::select('type')->distinct()->whereNotNull('type')->pluck('type')->sort()->values();
        $technicians = User::technicians()->orderBy('name')->get(['id', 'name', 'speciality', 'status']);

        $maintTypes = ['Préventive', 'Corrective', 'Inspection', 'Réglementaire', 'Urgence'];
        $priorities = [
            'critical' => 'Critique',
            'high' => 'Haute',
            'medium' => 'Moyenne',
            'low' => 'Basse',
        ];

        return view('maintenance-intelligence.index', compact(
            'latestScan',
            'totalInfrastructures',
            'totalMaintenances',
            'overdueCount',
            'dueSoonCount',
            'criticalInfrasCount',
            'zones',
            'infrastructureTypes',
            'technicians',
            'maintTypes',
            'priorities'
        ));
    }

    /**
     * Trigger a scan of maintenance risks and generate prioritized AI findings.
     */
    public function scan(Request $request): JsonResponse
    {
        try {
            $result = $this->riskAnalysisService->executeScan(persist: true);

            return response()->json([
                'success' => true,
                'message' => 'Analyse des risques de maintenance achevée avec succès.',
                'metrics' => $result['metrics'],
                'findings' => $result['findings'],
                'scanned_at' => Carbon::parse($result['scanned_at'])->format('d/m/Y à H:i'),
                'scan_id' => $result['scan_id'] ?? null,
                'status' => $result['status'],
            ]);
        } catch (GroqAiException $e) {
            Log::error('Groq AI scan error', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getUserFriendlyMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Maintenance risk scan failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur imprévue est survenue lors de l\'analyse des risques. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Generate an AI maintenance plan for a selected infrastructure and risk context.
     */
    public function generatePlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'infrastructure_id' => ['required', 'integer', 'exists:infrastructures,id'],
            'finding' => ['nullable', 'array'],
        ], [
            'infrastructure_id.required' => 'L\'infrastructure ciblée doit être renseignée.',
            'infrastructure_id.exists' => 'L\'infrastructure sélectionnée est introuvable.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $plan = $this->plannerService->generatePlan(
                (int) $request->input('infrastructure_id'),
                $request->input('finding')
            );

            return response()->json([
                'success' => true,
                'plan' => $plan,
                'message' => 'Plan de maintenance technique généré avec succès par l\'IA.',
            ]);
        } catch (GroqAiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getUserFriendlyMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Failed to generate maintenance plan', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible de formuler la proposition de plan de maintenance. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Confirm and persist the manager-reviewed maintenance plan into the database.
     */
    public function storeMaintenance(Request $request): JsonResponse
    {
        // 1. Rigorous Laravel validation adhering to application rules
        $validator = Validator::make($request->all(), [
            'infrastructure_id' => ['required', 'integer', 'exists:infrastructures,id'],
            'reference_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/i', 'unique:maintenances,reference_code'],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'technician')],
            'team' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'type' => ['required', 'string', 'in:Préventive,Corrective,Inspection,Réglementaire,Urgence'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'status' => ['required', 'in:planned,in_progress,completed,cancelled,reported'],
            'scheduled_at' => ['required', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'description' => ['nullable', 'string', 'max:3000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
            'next_maintenance_date' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'infrastructure_id.required' => 'Une infrastructure doit obligatoirement être sélectionnée pour cette intervention.',
            'infrastructure_id.exists' => 'L’infrastructure sélectionnée est introuvable.',
            'reference_code.regex' => 'Le code référence doit respecter le format standard (ex: MNT-2026-001).',
            'reference_code.unique' => 'Ce code de maintenance est déjà attribué à une autre opération.',
            'team.regex' => 'Le nom de l’équipe ne doit contenir que des lettres, espaces ou tirets.',
            'type.required' => 'Le type d’intervention (Préventive, Corrective, Inspection...) est obligatoire.',
            'type.in' => 'Le type d’intervention spécifié est invalide.',
            'priority.required' => 'Le niveau de priorité d’intervention doit être précisé.',
            'priority.in' => 'Le niveau de priorité spécifié est invalide.',
            'status.required' => 'Le statut opérationnel de la maintenance est requis.',
            'scheduled_at.required' => 'La date et l’heure planifiées sont obligatoires.',
            'cost.numeric' => 'Le montant du coût estimé doit être une valeur numérique.',
            'cost.min' => 'Le coût financier ne peut pas être négatif.',
            'duration_hours.numeric' => 'La durée d’intervention doit être un nombre d’heures valide.',
            'duration_hours.min' => 'La durée minimale d’intervention est de 0.1 heure (6 minutes).',
            'duration_hours.max' => 'La durée d’intervention ne peut pas excéder 500 heures.',
            'next_maintenance_date.after_or_equal' => 'La date de prochaine maintenance préconisée doit être planifiée à aujourd’hui ou dans le futur.',
        ]);

        // Custom validation logic
        $validator->after(function ($v) use ($request) {
            $status = $request->input('status');
            $scheduledAt = $request->input('scheduled_at');
            $nextDate = $request->input('next_maintenance_date');

            if ($status === 'planned' && $scheduledAt && strtotime($scheduledAt) < strtotime('today')) {
                $v->errors()->add(
                    'scheduled_at',
                    'Pour une maintenance au statut « Planifiée », la date prévisionnelle doit se situer aujourd’hui ou dans le futur.'
                );
            }

            if ($scheduledAt && $nextDate && strtotime($nextDate) <= strtotime($scheduledAt)) {
                $v->errors()->add(
                    'next_maintenance_date',
                    'La prochaine échéance recommandée doit être postérieure à la date d’intervention planifiée.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // 2. Format description with technical checklist if provided
        $description = trim((string) ($validated['description'] ?? ''));
        if (! empty($validated['checklist']) && is_array($validated['checklist'])) {
            $cleanChecklist = array_filter(array_map('trim', $validated['checklist']));
            if (! empty($cleanChecklist)) {
                $description .= "\n\n--- GAMME OPÉRATOIRE & CHECKLIST ---\n";
                foreach ($cleanChecklist as $item) {
                    $description .= "[ ] {$item}\n";
                }
            }
        }
        $validated['description'] = substr($description, 0, 3000);
        unset($validated['checklist']);

        // Auto reference code
        if (empty($validated['reference_code'])) {
            $nextId = (Maintenance::max('id') ?? 0) + 1;
            $validated['reference_code'] = sprintf('MNT-%s-%03d', date('Y'), $nextId);
        }

        // 3. Database transaction to persist and synchronize infrastructure
        $maintenance = DB::transaction(function () use ($validated) {
            $maint = Maintenance::create($validated);

            $infra = $maint->infrastructure;
            if ($infra) {
                if ($maint->status === 'completed') {
                    $infra->last_maintenance_date = now()->format('Y-m-d');
                    if ($infra->status === 'maintenance') {
                        $infra->status = 'operational';
                    }
                } elseif ($maint->status === 'in_progress') {
                    $infra->status = 'maintenance';
                }

                if (! empty($maint->next_maintenance_date)) {
                    $infra->next_maintenance_date = $maint->next_maintenance_date;
                }
                $infra->save();
            }

            return $maint;
        });

        return response()->json([
            'success' => true,
            'message' => "L'opération de maintenance « {$maintenance->reference_code} » a été planifiée avec succès.",
            'redirect_url' => route('maintenances.show', $maintenance),
            'maintenance_id' => $maintenance->id,
            'reference_code' => $maintenance->reference_code,
        ]);
    }
}
