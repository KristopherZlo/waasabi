<script nonce="{{ $csp_nonce ?? '' }}">
    try {
        const savedTheme = JSON.parse(localStorage.getItem('pageSettings') || '{}').theme;
        if (savedTheme === 'light' || savedTheme === 'dark') {
            document.documentElement.dataset.theme = savedTheme;
        }
    } catch {}
</script>
