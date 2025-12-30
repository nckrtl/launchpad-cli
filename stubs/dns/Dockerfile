FROM alpine:latest
RUN apk add --no-cache dnsmasq
ENTRYPOINT ["dnsmasq", "-k", "--address=/.test/127.0.0.1"]
