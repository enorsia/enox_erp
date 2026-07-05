<thead class="sr-thead">
    @foreach($table_headers['rows'] as $headerRow)
        <tr>
            @foreach($headerRow as $cell)
                <th colspan="{{ $cell['colspan'] ?? 1 }}"
                    rowspan="{{ $cell['rowspan'] ?? 1 }}"
                    class="tbl-th sr-th sr-th-{{ $cell['align'] ?? 'left' }} {{ $cell['class'] ?? '' }}">
                    {{ $cell['label'] }}
                </th>
            @endforeach
        </tr>
    @endforeach
</thead>
