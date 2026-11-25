@props(['zone'])
{{-- Times inside are drawn in the business's zone on the server (the truth, and what shows without JavaScript). Where the visitor's own zone differs,
     Alpine rewrites each <time> to it and keeps the business time in the tooltip. The business time stays in the page source either way. --}}
<div {{ $attributes }} x-data="{ biz: '{{ $zone }}' }" x-init="const mine = Intl.DateTimeFormat().resolvedOptions().timeZone; if (mine && mine !== biz) { $el.querySelectorAll('time[datetime]').forEach(t => { const o = t.dataset.show === 'full' ? { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' } : { hour: '2-digit', minute: '2-digit' }; const was = t.textContent.trim(); t.title = 'Business time (' + biz + '): ' + was; t.textContent = new Intl.DateTimeFormat(undefined, o).format(new Date(t.getAttribute('datetime'))) + ' your time'; }) }">
    {{ $slot }}
</div>
