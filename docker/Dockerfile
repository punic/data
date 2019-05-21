FROM alpine:3.7

RUN apk upgrade -U && \
    apk add --update --no-cache \
        git \
        git-lfs \
        openjdk8 \
        apache-ant \
        php7 \
        php7-json \
        php7-intl \
        php7-dom \
        php7-simplexml \
        php7-openssl

ENV PATH=$PATH:/punicdata/bin

WORKDIR /punicdata
