<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    @foreach ($data['duration_buckets'] ?? [] as $bucket)
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 text-center">
            <div class="text-2xl font-semibold">{{ number_format($bucket['count']) }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $bucket['label'] }}</div>
        </div>
    @endforeach
</div>
