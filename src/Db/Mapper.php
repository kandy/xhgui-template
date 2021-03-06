<?php

namespace XHGui\Db;

use DateInterval;
use DateTime;
use MongoDate;
use XHGui\Searcher\SearcherInterface;

class Mapper
{
    /**
     * Convert request data keys into mongo values.
     */
    public function convert(array $options): array
    {
        return [
            'conditions' => $this->buildConditions($options['conditions'] ?? []),
            'sort' => $this->buildSort($options),
            'direction' => $this->buildDirection($options),
            'perPage' => $options['perPage'] ?? SearcherInterface::DEFAULT_PER_PAGE,
        ];
    }

    /**
     * Convert the search parameters into the matching fields.
     *
     * Keeps the schema details out of the GET parameters.
     * String casts are uses to prevent mongo operator injection.
     */
    private function buildConditions(array $search): array
    {
        if (!$search) {
            return [];
        }

        if (!empty($search['limit_custom']) && $search['limit_custom'][0] === 'P') {
            $search['limit'] = $search['limit_custom'];
        }
        $hasLimit = (!empty($search['limit']) && $search['limit'] != -1);

        $conditions = [];
        if (!empty($search['date_start']) && !$hasLimit) {
            $conditions['meta.request_date']['$gte'] = (string)$search['date_start'];
        }
        if (!empty($search['date_end']) && !$hasLimit) {
            $conditions['meta.request_date']['$lte'] = (string)$search['date_end'];
        }
        if (!empty($search['server_name'])) {
            $conditions['meta.SERVER.SERVER_NAME'] = (string)$search['server_name'];
        }
        if (isset($search['simple_url'])) {
            $conditions['meta.simple_url'] = (string)$search['simple_url'];
        }
        if (!empty($search['request_start'])) {
            $conditions['meta.SERVER.REQUEST_TIME']['$gte'] = $this->convertDate($search['request_start']);
        }
        if (!empty($search['request_end'])) {
            $conditions['meta.SERVER.REQUEST_TIME']['$lte'] = $this->convertDate($search['request_end']);
        }

        if (!empty($search['remote_addr'])) {
            $conditions['meta.SERVER.REMOTE_ADDR'] = (string)$search['remote_addr'];
        }
        if (isset($search['cookie'])) {
            $conditions['meta.SERVER.HTTP_COOKIE'] = (string)$search['cookie'];
        }

        if ($hasLimit && $search['limit'][0] === 'P') {
            $date = new DateTime();
            try {
                $date->sub(new DateInterval($search['limit']));
                $conditions['meta.request_ts']['$gte'] = new MongoDate($date->getTimestamp());
            } catch (\Exception $e) {
                // Match a day in the future so we match nothing, as it's likely an invalid format
                $conditions['meta.request_ts']['$gte'] = new MongoDate(time() + 86400);
            }
        }

        if (isset($search['url'])) {
            // Not sure if letting people use regex here
            // is a good idea. Only one way to find out.
            $conditions['meta.url'] = [
                '$regex' => (string)$search['url'],
                '$options' => 'i',
            ];
        }

        return $conditions;
    }

    private function convertDate($dateString)
    {
        if (is_numeric($dateString)) {
            return (float) $dateString;
        }
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        if (!$date) {
            return $date;
        }

        return $date->getTimestamp();
    }

    private function buildDirection(array $options): string
    {
        if (empty($options['direction'])) {
            return SearcherInterface::DEFAULT_DIRECTION;
        }
        $valid = ['desc', 'asc'];
        if (in_array($options['direction'], $valid, true)) {
            return $options['direction'];
        }

        return 'desc';
    }

    /**
     * Get sort options for a paginated set.
     *
     * Whitelists to valid known keys.
     *
     * @param array $options pagination options including the sort key
     * @return array sort field & direction
     */
    private function buildSort(array $options): array
    {
        $direction = -1;
        if (isset($options['direction']) && $options['direction'] === 'asc') {
            $direction = 1;
        }

        $valid = ['time', 'wt', 'mu', 'cpu'];
        if (
            empty($options['sort']) ||
            (isset($options['sort']) && !in_array($options['sort'], $valid))
        ) {
            return ['meta.SERVER.REQUEST_TIME' => $direction];
        }
        if ($options['sort'] === 'time') {
            return ['meta.SERVER.REQUEST_TIME' => $direction];
        } elseif ($options['sort'] === 'wt') {
            return ['profile.main().wt' => $direction];
        } elseif ($options['sort'] === 'mu') {
            return ['profile.main().mu' => $direction];
        } elseif ($options['sort'] === 'cpu') {
            return ['profile.main().cpu' => $direction];
        }
    }
}
