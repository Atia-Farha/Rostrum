@extends('layouts.app')

@section('title', 'Session Records — Rostrum')

{{--
    The record table.

    A history page is a ledger, not a gallery. Every row is one debate
    session, and the reader arrives with one question — "what have I run,
    and how did it go?" — so the table is built to answer that in a
    single pass:

        1. the motion        what was debated (the record's own name)
        2. the metadata      format, side and band, as quiet tags
        3. the opponent      who argued against you
        4. the date          when it happened
        5. the status        adjudicated, or still running
        6. the score         the mark, with a meter under it
        7. the actions       open the report, or the arena

    Six columns, not eight. Format, side and difficulty used to have
    columns of their own, which squeezed the motion — the one thing
    being scanned for — into a narrow gutter and left every row reading
    as a table of metadata with the subject buried in it. They travel
    under the motion now, as tags, where they inform the name instead
    of competing with it.

    The page's own header carries no statistics. The overview already
    owns those, and a page that states the same fact twice in two
    shapes reads as clutter rather than as emphasis.
--}}

@section('content')

<div class="surf surf--wide" x-data="historyPage()">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="surf-head">
        <div class="surf-head-body">
            <span class="surf-eyebrow">Records</span>
            <h1 class="surf-title">Debate History</h1>
            <p class="surf-subtitle">
                Every session on record, with its adjudication score and transcript.
            </p>
        </div>

        <div class="surf-head-actions">
            <a href="{{ route('setup') }}" class="surf-btn surf-btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                New Debate
            </a>
        </div>
    </div>

    {{-- ── The ledger ──────────────────────────────────────────────────── --}}
    <div class="surf-panel">

        <div class="surf-panel-head">
            <h2 class="surf-panel-title">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>
                Sessions
            </h2>
            {{-- The count of what the table holds. It follows the active
                 tab and its filters, so it always agrees with the table
                 below it — a header saying "6 records" above a table
                 showing four rows is two numbers for one thing, and the
                 reader is left to work out which of them is the real
                 one. The server-rendered value is the default tab's
                 count, so the number does not shift when Alpine takes
                 over. --}}
            @php $defaultTabCount = $defaultTab === 'tournament' ? $tournamentCount : $sparringCount; @endphp
            <span class="surf-panel-meta" x-text="panelCount" aria-live="polite">
                {{ $defaultTabCount }} {{ \Illuminate\Support\Str::plural('session', $defaultTabCount) }}
            </span>
        </div>

        {{-- Toolbar: what is shown, then how it is narrowed. --}}
        <div class="surf-toolbar">
            <div class="surf-segment" role="tablist" aria-label="Filter by debate format">
                <button type="button" role="tab" class="surf-segment-btn"
                    x-bind:class="tab === 'tournament' ? 'is-active' : ''"
                    :aria-selected="tab === 'tournament'"
                    x-on:click="tab = 'tournament'">
                    Tournament
                    <span class="surf-segment-count" x-text="tournamentCount">0</span>
                </button>
                <button type="button" role="tab" class="surf-segment-btn"
                    x-bind:class="tab === 'sparring' ? 'is-active' : ''"
                    :aria-selected="tab === 'sparring'"
                    x-on:click="tab = 'sparring'">
                    Sparring
                    <span class="surf-segment-count" x-text="sparringCount">0</span>
                </button>
            </div>

            <div class="surf-toolbar-filters">
                <div class="surf-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" x-model="search" placeholder="Search by motion…" aria-label="Search debates by motion">
                    <button type="button" class="surf-search-clear" x-show="search" x-cloak @click="search = ''" aria-label="Clear search">&times;</button>
                </div>

                <div class="surf-field">
                    <select class="surf-select" x-model="statusFilter" aria-label="Filter by status">
                        <option value="all">All statuses</option>
                        <option value="adjudicated">Adjudicated</option>
                        <option value="in_progress">In progress</option>
                    </select>
                    <svg class="surf-field-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                {{-- The sort, once the column headers are gone. Below the
                     stacking breakpoint the table is a list of records
                     rather than a table, so the sortable headers that
                     carry the sort on a wide screen are no longer on
                     the page and the control has to move here. --}}
                <div class="surf-field surf-sort-field">
                    <label class="surf-sr-only" for="history-sort">Sort sessions</label>
                    <select id="history-sort" class="surf-select" x-model="sort" aria-label="Sort sessions">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="highest">Highest score</option>
                        <option value="lowest">Lowest score</option>
                    </select>
                    <svg class="surf-field-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
        </div>

        @if($debates->isEmpty())

            {{-- Nothing on record at all. It is a status region, so a
                 reader who lands here is told why the page is empty
                 rather than being left to work it out. --}}
            <div class="surf-empty" role="status">
                <div class="surf-empty-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" x2="8" y1="13" y2="13"/>
                        <line x1="16" x2="8" y1="17" y2="17"/>
                    </svg>
                </div>
                <h3 class="surf-empty-title">No sessions on record</h3>
                <p class="surf-empty-text">
                    Run your first debate and it will be logged here with its score and transcript.
                </p>
                <div class="surf-empty-action">
                    <a href="{{ route('setup') }}" class="surf-btn surf-btn-primary">Start First Debate</a>
                </div>
            </div>

        @else

            <div class="surf-table-wrap" x-show="visibleCount > 0">
                <table class="surf-table surf-table--records">
                    <caption class="surf-sr-only">
                        Every debate session on record, with its opponent, date, status and adjudication score.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" class="col-primary">Motion</th>
                            <th scope="col" class="col-person">Opponent</th>

                            {{-- Two sortable columns. The header is the
                                 control, so the sort direction is
                                 announced by the screen reader through
                                 aria-sort rather than only drawn. --}}
                            <th scope="col" class="col-date"
                                :aria-sort="sort === 'newest' ? 'descending' : (sort === 'oldest' ? 'ascending' : 'none')">
                                <button type="button"
                                        class="surf-sort"
                                        :class="{ 'is-active': sort === 'newest' || sort === 'oldest', 'is-desc': sort === 'newest' }"
                                        @click="sort = sort === 'newest' ? 'oldest' : 'newest'">
                                    Date
                                    <svg class="surf-sort-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
                                </button>
                            </th>

                            <th scope="col" class="col-status">Status</th>

                            <th scope="col" class="is-numeric col-score"
                                :aria-sort="sort === 'highest' ? 'descending' : (sort === 'lowest' ? 'ascending' : 'none')">
                                <button type="button"
                                        class="surf-sort surf-sort--numeric"
                                        :class="{ 'is-active': sort === 'highest' || sort === 'lowest', 'is-desc': sort === 'highest' }"
                                        @click="sort = sort === 'highest' ? 'lowest' : 'highest'">
                                    Score
                                    <svg class="surf-sort-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
                                </button>
                            </th>

                            <th scope="col" class="col-actions"><span class="surf-sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($debates as $debate)
                        @php
                            $motionText = $debate->motion->textFor($debate->language);
                            $adj        = $debate->adjudication;
                            $isBn       = $debate->language === 'bn';
                            $fontStyle  = $isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';
                            $href       = $adj
                                ? route('debates.feedback', $debate->id)
                                : route('debates.show', $debate->id);
                        @endphp

                        <tr data-mode="{{ $debate->mode }}"
                            data-status="{{ $adj ? 'adjudicated' : 'in_progress' }}"
                            data-motion="{{ $motionText }}"
                            data-score="{{ $adj ? $adj->total_score : '' }}"
                            data-created="{{ $debate->created_at->getTimestamp() }}"
                            x-show="matches($el)">

                            <td data-label="" class="col-primary">
                                <a href="{{ $href }}" class="surf-cell-primary surf-cell-clamp" style="{{ $fontStyle }}">{{ $motionText }}</a>

                                {{-- The facts that belong to the record but
                                     not to its name. A tag rather than a
                                     column, so the motion keeps the room. --}}
                                <span class="surf-record-meta">
                                    <span class="surf-tag">{{ ucfirst($debate->mode) }}</span>
                                    <span class="surf-tag">{{ ucfirst($debate->user_side) }}</span>
                                    <span class="surf-tag">{{ ucfirst(str_replace('_', ' ', $debate->difficulty)) }}</span>

                                    {{-- The language is tagged only when it
                                         is not the default. English is
                                         every session's baseline and
                                         repeating it on every row is
                                         noise; a Bangla session is worth
                                         naming. --}}
                                    @if ($isBn)
                                        <span class="surf-tag">Bangla</span>
                                    @endif
                                </span>
                            </td>

                            <td data-label="Opponent" class="col-person">{{ $debate->persona->name }}</td>

                            <td data-label="Date" class="col-date">
                                {{-- The date and the time are wrapped in
                                     one box so the stacked layout cannot
                                     split them across the row. --}}
                                <span class="surf-cell-date">
                                    <span>{{ $debate->created_at->format('d M Y') }}</span>
                                    <span class="surf-cell-sub">{{ $debate->created_at->format('H:i') }}</span>
                                </span>
                            </td>

                            <td data-label="Status" class="col-status">
                                @if ($adj)
                                    <span class="surf-pill surf-pill--done">Adjudicated</span>
                                @else
                                    <span class="surf-pill surf-pill--live">In progress</span>
                                @endif
                            </td>

                            <td data-label="Score" class="is-numeric col-score">
                                @if ($adj)
                                    {{-- The figure is the score; the meter
                                         under it says where that figure
                                         sits between zero and a hundred.
                                         The meter supports the number
                                         rather than replacing it. --}}
                                    <span class="surf-score-cell">
                                        <span class="surf-score">
                                            <span class="surf-score-value">{{ $adj->total_score }}</span>
                                            <span class="surf-score-denom">/100</span>
                                        </span>
                                        <span class="surf-meter" role="presentation">
                                            <span class="surf-meter-fill" style="width: {{ $adj->total_score }}%;"></span>
                                        </span>
                                    </span>
                                @else
                                    <span class="surf-cell-status" aria-label="Not yet scored">&mdash;</span>
                                @endif
                            </td>

                            <td data-label="" class="col-actions">
                                <div class="surf-row-actions">
                                    @if ($adj)
                                        {{-- The report is the destination
                                             worth a label: it is what the
                                             reader came to this row for.
                                             The arena beside it is a
                                             second look at the same
                                             session, so it carries an
                                             icon and a name for the
                                             screen reader instead of
                                             another word. --}}
                                        <a href="{{ route('debates.feedback', $debate->id) }}" class="surf-btn surf-btn-secondary surf-btn-sm">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            Report
                                        </a>
                                        <a href="{{ route('debates.show', $debate->id) }}" class="surf-btn surf-btn-outline surf-btn-sm surf-btn-icon" title="Open the arena" aria-label="Open the arena">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                        </a>
                                    @else
                                        <a href="{{ route('debates.show', $debate->id) }}" class="surf-btn surf-btn-primary surf-btn-sm">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                            Resume
                                        </a>
                                    @endif

                                    @if(in_array($debate->id, $deletableIds, true))
                                    <form action="{{ route('debates.destroy', $debate->id) }}" method="POST"
                                          x-ref="deleteForm{{ $loop->index }}"
                                          style="display:contents;"
                                          @submit.prevent="confirmAction({
                                              title: 'Delete Debate Session',
                                              message: 'Are you sure you want to permanently delete this debate session? This action cannot be undone.',
                                              confirmText: 'Delete Session'
                                          }).then(() => $refs.deleteForm{{ $loop->index }}.submit())">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="surf-btn surf-btn-danger surf-btn-sm surf-btn-icon" title="Delete session" aria-label="Delete session">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Filtered empty state. One state for every reason the
                 list can come up short, because three of them used to
                 be separate blocks that had to agree about which
                 filter was active. --}}
            <div class="surf-empty" x-show="visibleCount === 0" x-cloak role="status">
                <div class="surf-empty-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <h3 class="surf-empty-title" x-text="emptyTitle()"></h3>
                <p class="surf-empty-text" x-text="emptyText()"></p>
                <div class="surf-empty-action">
                    <button type="button" class="surf-btn surf-btn-secondary" x-show="hasActiveFilters()" @click="resetFilters()">Clear filters</button>
                    <a href="{{ route('setup') }}" class="surf-btn surf-btn-primary">New Debate</a>
                </div>
            </div>

            {{-- The count of what is on screen. A table that lists
                 records without saying how many leaves the reader
                 wondering whether the list is complete or cut off. --}}
            <div class="surf-table-foot">
                <span class="surf-table-foot-meta" aria-live="polite" x-text="summary()"></span>

                <button type="button" class="surf-btn surf-btn-outline surf-btn-sm"
                        x-show="hasActiveFilters()" x-cloak
                        @click="resetFilters()">
                    Clear filters
                </button>
            </div>

        @endif
    </div>

</div>

<script>
    /**
     * The record table's client-side state.
     *
     * The rows are rendered by the server — the ledger is the page's
     * content, and an empty <tbody> would be a blank screen for a
     * reader without JavaScript. This scope only does what a server
     * round-trip is too slow for: narrowing the list, sorting it, and
     * keeping the counts honest.
     */
    function historyPage() {
        return {
            tab: '{{ $defaultTab }}',
            search: '',
            statusFilter: 'all',
            sort: 'newest',

            visibleCount: {{ $debates->count() }},
            tabTotal: {{ $defaultTab === 'tournament' ? $tournamentCount : $sparringCount }},
            tournamentCount: {{ $tournamentCount }},
            sparringCount: {{ $sparringCount }},

            init() {
                this.$watch('tab', () => this.refresh());
                this.$watch('search', () => this.refresh());
                this.$watch('statusFilter', () => this.refresh());
                this.$watch('sort', () => this.applySort());
                this.refresh();
            },

            /** Every rendered row, in document order. */
            rows() {
                return Array.from(document.querySelectorAll('.surf-table--records tbody tr[data-mode]'));
            },

            /** Whether a row survives the format, status and search filters. */
            matches(el) {
                if ((el.dataset.mode || '') !== this.tab) return false;
                if (this.statusFilter !== 'all' && (el.dataset.status || '') !== this.statusFilter) return false;
                const q = this.search.trim().toLowerCase();
                if (!q) return true;
                return (el.dataset.motion || '').toLowerCase().includes(q);
            },

            /**
             * Whether a row survives the status and search filters,
             * ignoring the format. This is what the segment counts
             * report: each segment says how many of its own sessions
             * the current search and status leave behind.
             */
            matchesFilter(el) {
                const q = this.search.trim().toLowerCase();
                const searchMatch = !q || (el.dataset.motion || '').toLowerCase().includes(q);
                const statusMatch = this.statusFilter === 'all' || (el.dataset.status || '') === this.statusFilter;
                return searchMatch && statusMatch;
            },

            refresh() {
                requestAnimationFrame(() => {
                    const rows = this.rows();

                    this.tournamentCount = rows.filter(el => el.dataset.mode === 'tournament' && this.matchesFilter(el)).length;
                    this.sparringCount   = rows.filter(el => el.dataset.mode === 'sparring' && this.matchesFilter(el)).length;
                    this.tabTotal        = rows.filter(el => el.dataset.mode === this.tab).length;
                    this.visibleCount    = rows.filter(el => this.matches(el)).length;

                    this.applySort();
                });
            },

            /**
             * Re-order the rows in place.
             *
             * The rows are moved rather than re-rendered so that the
             * table keeps its server-rendered markup — and its links,
             * forms and labels — through every sort.
             */
            applySort() {
                const tbody = document.querySelector('.surf-table--records tbody');
                if (!tbody) return;

                const byDate = (a, b) => Number(a.dataset.created) - Number(b.dataset.created);

                // An unscored session has no number to rank, so it is
                // always ranked last rather than being read as a zero.
                const byScore = (a, b) => {
                    const sa = a.dataset.score === '' ? null : Number(a.dataset.score);
                    const sb = b.dataset.score === '' ? null : Number(b.dataset.score);
                    if (sa === null && sb === null) return 0;
                    if (sa === null) return 1;
                    if (sb === null) return -1;
                    return this.sort === 'lowest' ? sa - sb : sb - sa;
                };

                let comparator;
                switch (this.sort) {
                    case 'oldest':  comparator = byDate; break;
                    case 'highest': comparator = byScore; break;
                    case 'lowest':  comparator = byScore; break;
                    default:        comparator = (a, b) => byDate(b, a);
                }

                this.rows().sort(comparator).forEach(el => tbody.appendChild(el));
            },

            /** Whether any filter is narrowing the list. */
            hasActiveFilters() {
                return this.search.trim() !== '' || this.statusFilter !== 'all';
            },

            /** Put every filter back to its default. */
            resetFilters() {
                this.search = '';
                this.statusFilter = 'all';
                this.sort = 'newest';
            },

            /** The headline of the empty state, which depends on why the list is empty. */
            emptyTitle() {
                if (this.hasActiveFilters()) return 'No sessions match these filters';
                return this.tab === 'tournament' ? 'No tournament sessions yet' : 'No sparring sessions yet';
            },

            emptyText() {
                if (this.hasActiveFilters()) {
                    return 'Nothing matches the current search and status. Clear them to see the rest of your record.';
                }
                return this.tab === 'tournament'
                    ? 'Tournament rounds are judged on Matter, Manner and Method and produce a full report.'
                    : 'Sparring rounds are informal and free-flowing — good for practising quick argument construction.';
            },

            /** The count of what is on screen, in words. */
            summary() {
                const noun = this.tab === 'tournament' ? 'tournament session' : 'sparring session';

                if (this.visibleCount === this.tabTotal) {
                    return this.tabTotal + ' ' + (this.tabTotal === 1 ? noun : noun + 's');
                }

                // The noun agrees with the total, not with the visible
                // count: "showing 1 of 4 tournament sessions" is one
                // session out of four, and calling the four a
                // singular would misstate the record.
                return 'Showing ' + this.visibleCount + ' of ' + this.tabTotal + ' ' + (this.tabTotal === 1 ? noun : noun + 's');
            },

            /**
             * The count in the panel header.
             *
             * It reports what is on screen, so it always agrees with
             * the active tab's badge and with the table below it. A
             * header saying "6 records" above a table showing four rows
             * is two numbers for one thing, and the reader is left to
             * work out which of them is the real one.
             *
             * The total is not lost: the footer spells out how many of
             * the tab's sessions the current filters leave behind.
             */
            panelCount() {
                const n = this.visibleCount;
                return n + ' ' + (n === 1 ? 'session' : 'sessions');
            },
        };
    }
</script>

@endsection
