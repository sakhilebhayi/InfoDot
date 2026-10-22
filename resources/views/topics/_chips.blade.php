{{-- The topics an item is filed under, as links to the topic pages. "Untagged" is shown as it is: nothing has been guessed. --}}
@php($filed = $item->topics->sortBy(['sort_order', 'id']))
<ul class="ix-topics" aria-label="Topics">
    @forelse ($filed as $filedTopic)
        <li><a class="ix-topic" href="{{ $filedTopic->url() }}">{{ $filedTopic->name }}</a></li>
    @empty
        <li><span class="ix-topic ix-topic--none">Untagged</span></li>
    @endforelse
</ul>
