@if ($paginator->hasPages())
    <div class="table_pagination d-md-flex align-items-center justify-content-between mt-2 px-2">
        <div class="pagination_total_text">
            <p class="mb-2 mb-md-0">Showing <span>{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }}</span> of
                <span>{{ $paginator->total() }}</span>
            </p>
        </div>
        <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-end mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled">
                        <span class="page-link">Previous</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link"
                            href="{{ $paginator->previousPageUrl() }}@if (http_build_query(request()->except('page'))) &{{ http_build_query(request()->except('page')) }} @endif"
                            rel="prev">
                            Previous
                        </a>
                    </li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active">
                                    <span class="page-link">{{ $page }}</span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link"
                                        href="{{ $url }}@if (http_build_query(request()->except('page'))) &{{ http_build_query(request()->except('page')) }} @endif">
                                        {{ $page }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link"
                            href="{{ $paginator->nextPageUrl() }}@if (http_build_query(request()->except('page'))) &{{ http_build_query(request()->except('page')) }} @endif"
                            rel="next">
                            Next
                        </a>
                    </li>
                @else
                    <li class="page-item disabled">
                        <span class="page-link">Next</span>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
@endif
