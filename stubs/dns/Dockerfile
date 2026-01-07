FROM alpine:latest
RUN apk add --no-cache dnsmasq bind-tools
ARG HOST_IP=127.0.0.1
ARG TLD=test
ENV HOST_IP=${HOST_IP}
ENV TLD=${TLD}
HEALTHCHECK --interval=10s --timeout=5s --start-period=5s --retries=3 \
    CMD nslookup healthcheck.$TLD 127.0.0.1 || exit 1
ENTRYPOINT ["sh", "-c", "dnsmasq -k --address=/.$TLD/$HOST_IP"]
