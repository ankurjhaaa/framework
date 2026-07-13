<?php

namespace Kite\Core;

/**
 * The Paginator.
 * Handles pagination logic, stores records, and renders pagination links.
 * Implements IteratorAggregate and Countable to behave like an array in loops.
 */
class Paginator implements \IteratorAggregate, \Countable
{
    public array $items;
    public int $total;
    public int $perPage;
    public int $currentPage;
    public int $lastPage;
    public Request $request;

    public function __construct(array $items, int $total, int $perPage, int $currentPage, Request $request)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->request = $request;
    }

    /**
     * Get the items for the current page.
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     */
    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    /**
     * Determine if the paginator is on the first page.
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    /**
     * Determine if there are more pages after the current page.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Get the URL for a given page number.
     * Preserves existing query string parameters (e.g., search).
     */
    public function url(int $page): string
    {
        $query = $this->request->query;
        $query['page'] = $page;
        return $this->request->uri . '?' . http_build_query($query);
    }

    /**
     * Get the URL for the previous page.
     */
    public function previousPageUrl(): ?string
    {
        if ($this->onFirstPage()) {
            return null;
        }
        return $this->url($this->currentPage - 1);
    }

    /**
     * Get the URL for the next page.
     */
    public function nextPageUrl(): ?string
    {
        if (!$this->hasMorePages()) {
            return null;
        }
        return $this->url($this->currentPage + 1);
    }

    /**
     * Render the pagination links using the system.pagination view.
     */
    public function links(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        ob_start();
        View::make('system.pagination', ['paginator' => $this]);
        return ob_get_clean();
    }

    /**
     * Allow iterating over items using foreach ($paginator as $item).
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Count the number of items on the current page.
     */
    public function count(): int
    {
        return count($this->items);
    }
}
