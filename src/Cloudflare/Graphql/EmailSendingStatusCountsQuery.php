<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\Graphql;

final class EmailSendingStatusCountsQuery
{
    public const string QUERY = <<<'GRAPHQL'
query EmailSendingByStatus($zoneTag: string!, $start: Date!, $end: Date!, $limit: uint64!) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      emailSendingAdaptiveGroups(
        filter: { date_geq: $start, date_leq: $end }
        limit: $limit
        orderBy: [date_DESC]
      ) {
        count
        dimensions {
          date
          status
        }
      }
    }
  }
}
GRAPHQL;
}
