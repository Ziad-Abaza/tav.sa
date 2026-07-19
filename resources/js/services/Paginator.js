/**
 * Pagination state class.
 * Ported from wm-saas-v2 (originally from Concord CRM).
 */

const _settings = window.tablePaginationSettings ?? {}
const _defaultPerPage = _settings.current ?? 10
const _defaultOptions = _settings.options?.filter(v => v !== 'all').map(Number) ?? [10, 50, 100, 500, 1000]

const DEFAULT_STATE = {
    data: [],
    current_page: 1,
    per_page: _defaultPerPage,
    per_page_options: _defaultOptions,
    total: 0,
    last_page: 1,
    from: 1,
    to: 1,
}

export class Paginator {
    constructor(initial = {}) {
        this.state = Object.assign({}, DEFAULT_STATE, initial)
    }

    get items() {
        return this.state.data
    }

    get currentPage() {
        return this.state.current_page
    }

    set currentPage(value) {
        this.state.current_page = value
    }

    get perPage() {
        return this.state.per_page
    }

    set perPage(value) {
        this.state.per_page = value
        this.currentPage = 1
    }

    get total() {
        return this.state.total
    }

    get lastPage() {
        return this.state.last_page
    }

    get from() {
        return this.state.from
    }

    get to() {
        return this.state.to
    }

    get perPageOptions() {
        return this.state.per_page_options
    }

    get hasPagination() {
        return this.lastPage > 1
    }

    get hasNextPage() {
        return this.currentPage < this.lastPage
    }

    get hasPreviousPage() {
        return this.currentPage > 1
    }

    isEmpty() {
        return this.items.length === 0
    }

    isNotEmpty() {
        return this.items.length > 0
    }

    isCurrentPage(page) {
        return this.currentPage === page
    }

    page(value) {
        this.currentPage = value
    }

    nextPage() {
        this.page(this.currentPage + 1)
    }

    previousPage() {
        this.page(this.currentPage - 1)
    }

    setState(response) {
        const meta = 'meta' in response ? response.meta : response
        const data = response.data
        this.state = Object.assign({}, this.state, meta, { data })
    }

    flush() {
        this.state = Object.assign({}, DEFAULT_STATE)
    }

    buildLinks(currentPage, pageCount, delta = 3) {
        const range = []

        for (
            let i = Math.max(2, currentPage - delta);
            i <= Math.min(pageCount - 1, currentPage + delta);
            i++
        ) {
            range.push(i)
        }

        if (currentPage - delta > 2) {
            range.unshift('...')
        }

        if (currentPage + delta < pageCount - 1) {
            range.push('...')
        }

        range.unshift(1)

        if (pageCount > 1) {
            range.push(pageCount)
        }

        return range
    }

    get pagination() {
        return this.buildLinks(this.currentPage, this.lastPage)
    }

    get shouldRenderLinks() {
        return this.pagination.includes(this.currentPage)
    }
}
