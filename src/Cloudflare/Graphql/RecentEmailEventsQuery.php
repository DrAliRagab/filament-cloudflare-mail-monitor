<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\Graphql;

final class RecentEmailEventsQuery
{
    public const string QUERY = <<<'GRAPHQL'
query RecentEmailEvents($zoneTag: string!, $start: Time!, $end: Time!, $limit: uint64!, $datetimeBefore: Time) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      emailSendingAdaptive(
        filter: { datetime_geq: $start, datetime_leq: $end, datetime_lt: $datetimeBefore }
        limit: $limit
        orderBy: [datetime_DESC]
      ) {
        datetime
        from
        to
        subject
        status
        eventType
        sendingDomain
        messageId
        sessionId
        errorCause
        errorDetail
        arc
        dkim
        dmarc
        spf
        isSpam
        isNDR
      }
    }
  }
}
GRAPHQL;
}
