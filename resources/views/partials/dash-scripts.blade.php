@push('vendor')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script src="{{ asset('assets/vendor/chartjs/chartjs-plugin-datalabels.min.js') }}"></script>
<script src="{{ asset('assets/vendor/xlsx/xlsx.full.min.js') }}" defer></script>
@endpush
@push('scripts')
<script>window.PAGE = @json($page);</script>
<script src="{{ asset('assets/js/dashboards.js') }}?v={{ filemtime(public_path('assets/js/dashboards.js')) }}"></script>
@endpush
