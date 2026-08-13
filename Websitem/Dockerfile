FROM python:3.13-slim

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .
# collected at build time, not on every boot: nothing about them changes at run
RUN DJANGO_DEBUG=0 python manage.py collectstatic --noinput

CMD ["sh", "-c", "gunicorn websitem.wsgi:application --bind 0.0.0.0:${PORT:-8000} --workers 2"]
