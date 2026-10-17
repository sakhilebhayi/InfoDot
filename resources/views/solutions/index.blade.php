<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Solutions</h1>
            <p class="ix-lede">Step-by-step answers written down so the same problem is only solved once.</p>
        </div>
        <a href="{{ route('add') }}" class="ix-btn ix-btn--primary">
            <span class="material-symbols-rounded" aria-hidden="true">add</span>Add a solution
        </a>
    </div>

    @if ($solutions->isNotEmpty())
        <div class="ix-tablewrap">
            <table class="ix-table">
                <thead>
                    <tr><th>Solution</th><th>Author</th><th class="num">Steps</th><th>Time</th><th class="num">Replies</th><th>Added</th></tr>
                </thead>
                <tbody>
                @foreach ($solutions as $solution)
                    <tr>
                        <td>
                            <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="title">{{ $solution->solution_title }}</a>
                            <p class="sub">{{ $solution->solution_description }}</p>
                        </td>
                        <td class="muted">{{ $solution->user->name ?? 'Former member' }}</td>
                        <td class="num">{{ $solution->steps ?? 0 }}</td>
                        <td class="muted">
                            @if ($solution->duration_type === 'infinite') Unknown
                            @else {{ $solution->duration }} {{ Str::plural(rtrim($solution->duration_type, 's'), $solution->duration) }}
                            @endif
                        </td>
                        <td class="num">{{ $solution->comments_count }}</td>
                        <td class="muted">{{ $solution->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <section class="ix-empty">
            <h2>No solutions yet</h2>
            <p>When you work something out that others will hit again, write it down as steps. It shows up here and in search.</p>
            <a href="{{ route('add') }}" class="ix-btn ix-btn--primary">Add the first solution</a>
        </section>
    @endif
</x-app-layout>
