<x-app-layout>
    @php($none = ['questions' => 0, 'answered' => 0, 'unanswered' => 0, 'solutions' => 0])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Topics</h1>
            <p class="ix-lede">Questions and solutions, filed by subject. The counts are public ones: what is published to everyone and has not been hidden. Company-only work is not counted.</p>
        </div>
        <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">
            <span class="material-symbols-rounded" aria-hidden="true">add_comment</span>Ask a question
        </a>
    </div>

    @if ($topics->isEmpty())
        <section class="ix-empty">
            <h2>No topics have been set up yet</h2>
            <p>A moderator adds them. Until then questions and solutions can still be read, but not filed.</p>
        </section>
    @else
        <div class="ix-tablewrap">
            <table class="ix-table ix-topictable">
                <caption class="ix-sr">Public questions and solutions by topic</caption>
                <thead>
                    <tr>
                        <th scope="col">Topic</th>
                        <th scope="col" class="num">Questions</th>
                        <th scope="col" class="num">Answered</th>
                        <th scope="col" class="num">Unanswered</th>
                        <th scope="col" class="num">Solutions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topics as $topic)
                        @php($c = $counts->get($topic->id, $none))
                        <tr>
                            <th scope="row" data-label="Topic">
                                <a class="title" href="{{ $topic->url() }}">{{ $topic->name }}</a>
                                @if (filled($topic->description))<span class="sub">{{ $topic->description }}</span>@endif
                            </th>
                            <td class="num" data-label="Questions">{{ $c['questions'] }}</td>
                            <td class="num" data-label="Answered">{{ $c['answered'] }}</td>
                            <td class="num" data-label="Unanswered">{{ $c['unanswered'] }}</td>
                            <td class="num" data-label="Solutions">{{ $c['solutions'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="ix-hint ix-topichint">
            Answered means solved, or answered by someone other than the asker. Not yet filed under a topic:
            <a href="{{ route('questions', ['topic' => 'none']) }}">{{ $untagged['questions'] }} public {{ Str::plural('question', $untagged['questions']) }}</a>
            and <a href="{{ route('solutions', ['topic' => 'none']) }}">{{ $untagged['solutions'] }} public {{ Str::plural('solution', $untagged['solutions']) }}</a>.
            Older work stays untagged until a moderator files it.
        </p>
    @endif
</x-app-layout>
