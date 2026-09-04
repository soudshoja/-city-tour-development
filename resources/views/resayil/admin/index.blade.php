<x-app-layout>
    {{--
        Settings -> WhatsApp — standalone entry point (route: resayil-admin.index,
        /settings/whatsapp). Still a real, bookmarkable URL; the redesign's main
        change is that the Settings sidebar no longer treats this as a page to
        navigate away to — see settings/index.blade.php, which @includes the
        same _panel partial as its "WhatsApp" tab instead of linking out here.

        All actual content lives in resayil/admin/_panel.blade.php so the two
        entry points render identically rather than drifting apart.
    --}}
    @include('resayil.admin._panel')
</x-app-layout>
