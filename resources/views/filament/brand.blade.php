{{--
    The brand wherever Filament shows a logo: the sidebar, the phone's topbar and the
    sign-in page. The hospital's seal (the one on the leave form), the app's name, and
    whose it is. The seal is decorative: the name beside it says the same.
--}}
<span class="dtr-brand">
    <img src="{{ asset('images/trc-caraga-logo.png') }}" alt="" class="dtr-brand-mark">
    <span class="dtr-brand-text">
        <span class="dtr-brand-name">{{ filament()->getBrandName() }}</span>
        <span class="dtr-brand-sub">TRC Caraga</span>
    </span>
</span>
