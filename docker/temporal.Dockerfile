FROM temporalio/auto-setup:1.23
USER root
RUN apk add --no-cache postgresql-client
USER temporal
