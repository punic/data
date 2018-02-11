FROM alpine:3.7

RUN apk upgrade -U && \
    apk add --update --no-cache \
        subversion \
        openjdk8 \
        apache-ant \
        php7 \
        php7-json \
        php7-intl \
        php7-dom

ENV PATH=$PATH:/punicdata/bin

WORKDIR /punicdata
