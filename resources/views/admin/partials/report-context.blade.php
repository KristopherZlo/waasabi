@if (!empty($context))
    <details class="admin-report-context" data-report-context>
        <summary>
            <span>{{ __('ui.admin.report_summary', [
                'count' => $context['reports_count'],
                'reporters' => $context['reporters_count'],
            ]) }}</span>
            <strong>{{ __('ui.admin.report_weight') }} {{ number_format((float) $context['weight_total'], 1) }} / {{ number_format((float) $context['weight_threshold'], 1) }}</strong>
        </summary>
        <div class="admin-report-context__reasons">
            @foreach ($context['reasons'] as $reason)
                @php
                    $reasonKey = 'ui.report.reason_'.$reason['reason'];
                    $reasonLabel = __($reasonKey);
                @endphp
                <div>
                    <strong>{{ $reasonLabel === $reasonKey ? $reason['reason'] : $reasonLabel }} × {{ $reason['count'] }}</strong>
                    @if (!empty($reason['details']))
                        <p>{{ $reason['details'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </details>
@endif
