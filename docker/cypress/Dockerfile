FROM cypress/included:12.17.2 AS cypress-run

ENV CYPRESS_CACHE_FOLDER=/home/node/.cache/Cypress
RUN mv /root/.cache /home/node/.cache
RUN chown -R node:node /home/node/.cache

USER node

RUN cypress verify

FROM cypress-run AS cypress-open

USER root
RUN apt-get update && \
    apt-get install --no-install-recommends -y \
    icewm \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

USER node
RUN mkdir /home/node/.icewm
COPY icewm-preferences.ini /home/node/.icewm/preferences
COPY --chmod=755 cypress-open.sh /home/node/cypress-open

ENTRYPOINT ["/home/node/cypress-open"]
