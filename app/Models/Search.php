<?php

namespace App\Models;

trait Search
{
    private function buildWildCards($term)
    {
        if ($term == '') {
            return $term;
        }

        // Strip MySQL reserved symbols
        $reservedSymbols = ['-', '+', '<', '>', '@', '(', ')', '~'];
        $term = str_replace($reservedSymbols, '', $term);

        $words = explode(' ', $term);
        foreach ($words as $idx => $word) {
            // Add operators so we can leverage the boolean mode of
            // fulltext indices.
            $words[$idx] = '+'.$word.'*';
        }
        $term = implode(' ', $words);

        return $term;
    }

    protected function scopeSearch($query, $term)
    {
        $connection = $query->getConnection()->getDriverName();

        if ($connection === 'sqlite') {
            $query->where(function ($q) use ($term) {
                foreach ($this->searchable as $index => $column) {
                    if ($index === 0) {
                        $q->where($column, 'like', "%{$term}%");
                    } else {
                        $q->orWhere($column, 'like', "%{$term}%");
                    }
                }
            });

            return $query;
        }

        $columns = implode(',', $this->searchable);

        // Boolean mode allows us to match john* for words starting with john
        // (https://dev.mysql.com/doc/refman/5.6/en/fulltext-boolean.html)
        $query->whereRaw(
            "MATCH ({$columns}) AGAINST (? IN BOOLEAN MODE)",
            $this->buildWildCards($term)
        );

        return $query;
    }
}
