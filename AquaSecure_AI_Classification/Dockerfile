FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app
COPY AquaSecure_AI_Classification/requirements.txt ./requirements.txt
RUN python -m pip install --no-cache-dir -r requirements.txt \
    && groupadd --system app \
    && useradd --system --gid app --home-dir /app app

COPY AquaSecure_AI_Classification/app.py AquaSecure_AI_Classification/model_signalement.joblib ./
USER app

EXPOSE 5000
CMD ["python", "-m", "flask", "--app", "app", "run", "--host=0.0.0.0", "--port=5000"]
