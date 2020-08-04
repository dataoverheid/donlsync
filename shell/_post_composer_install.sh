#!/usr/bin/env bash


set -e
set -u


ENV_SOURCE=./.env.dist
ENV_FILE=./.env
EMAIL_FILE=./config/email_recipients.json
LOG_DIR=./log
VIEW_CACHE_DIR=./cache/views


cd "$(dirname "$0")/../"


if [ ! -f ${ENV_FILE} ]; then
    echo "File ${ENV_FILE} does not exist; creating one from ${ENV_SOURCE}."
    echo "You should now update the ${ENV_FILE} file with the correct data."

    cp "${ENV_SOURCE}" "${ENV_FILE}"
fi


if [ ! -d ${LOG_DIR} ]; then
    echo "Creating logging directory at ${LOG_DIR}"

    mkdir "${LOG_DIR}"
fi


if [ ! -d ${VIEW_CACHE_DIR} ]; then
    echo "Creating cache directory at ${VIEW_CACHE_DIR}"

    mkdir -p "${VIEW_CACHE_DIR}"
else
    echo "Clearing Blade view cache"

    find ${VIEW_CACHE_DIR} -type f -name "*.php" -delete
fi


if [ ! -f ${EMAIL_FILE} ]; then
    echo "Creating JSON file at ${EMAIL_FILE}."
    echo "This file contains the email addresses to which the daily log"
    echo "summaries will be sent."
    echo ""
    echo "Add a recipient by adding the following to the JSON list:"
    echo "{"
    echo "  \"name\": \"{name}\""
    echo "  \"email\": \"{email address}\""
    echo "}"

    echo "[]" > "${EMAIL_FILE}"
fi
