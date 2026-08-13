# The platform, its database and its mail sink in one container, because the
# free tier gives one container and one port. No composer step: vendor/ is
# committed, exactly as it was on the shared host this app was written for.
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
		php-cli php-mysql php-mbstring php-xml php-zip php-gd php-curl \
		mariadb-server python3 python3-aiosmtpd tzdata ca-certificates \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY my.cnf /etc/mysql/conf.d/zz-demo.cnf
COPY . /app
# These files come off a Windows checkout as 0777. MariaDB silently ignores a
# world-writable config file — the tuning below would never have been applied.
RUN chmod 0644 /etc/mysql/conf.d/zz-demo.cnf \
	&& chmod +x /app/entrypoint.sh /app/janitor.sh

CMD ["/app/entrypoint.sh"]
