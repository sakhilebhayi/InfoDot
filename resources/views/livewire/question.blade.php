<div class="max-w-full px-4 pt-6 mx-auto">
    <section class="rounded-3xl border border-[#434656]/35 bg-[rgba(49,57,77,0.55)] p-6 shadow-[0_12px_32px_rgba(0,0,0,0.28)] transition hover:-translate-y-0.5" style="backdrop-filter:blur(20px);">
        <blockquote>
            <cite class="inline-flex items-center not-italic">
                <div class="flex items-center">
                    @if ($question->user->profile_photo_path != null)
                        <img src="{{ $question->user->profile_photo_url }}" class="h-8 w-8 rounded-full object-cover" alt="{{ $question->user->name }}">
                    @else
                        <img class="h-8 w-8 rounded-full object-cover" src="{{ $question->user->avatar() }}" alt="{{ $question->user->name }}" />
                    @endif
                    <div class="ml-4 text-sm">
                        <a href="{{ route('profile.show', ['id' => $question->user->id]) }}" class="font-semibold capitalize text-[#dae2fd]"><strong>{{ $question->user->name }}</strong></a>
                        <small class="mt-1 text-[#8d90a2]">{{ $question->created_at->diffForHumans() }}.</small>
                    </div>
                </div>
            </cite>
        </blockquote>
        <hr class="my-3 border-[#434656]/30" />
        <div class="w-full sm:items-center">
            <div class="relative">
                <div class="aspect-w-1 aspect-h-1">
                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}">
                        <p class="text-xl font-bold sm:text-2xl capitalize text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">
                            {{ $question->question }}
                        </p>
                        <p class="capitalize text-[#c3c5d8] mt-2">{{ $question->description }}</p>
                    </a>
                </div>
            </div>
        </div>
        <hr class="my-3 border-[#434656]/30" />
        <div class="w-full grid grid-cols-3 gap-4 mx-auto">

            <div class="text-[#b7c8e1] flex justify-center">
                <a href="#" class="inline-flex items-center {{ $question->likes()->where('user_id', Auth::id())->exists() ? 'text-[#b6c4ff]' : '' }}" wire:click.prevent="storeLike">
                    <i class="fa fa-thumbs-up mx-1" aria-hidden="true"></i> {{ $question->likes()->count() }}
                </a>
            </div>

            <div class="text-[#b7c8e1] flex justify-center">
                <i class="fa fa-comment mt-1 mx-1" aria-hidden="true"></i> {{ $question->comments()->count() }}
            </div>

            <div class="flex justify-center">
                @if(Auth()->user()->id === $model->user->id)
                    <a href="#" aria-label="{{ $model->status == 0 ? 'Mark as solved' : 'Marked as solved' }}" class="inline-flex items-center
                    {{ $model->status == 0 ? 'text-red-400' : 'text-green-400' }}" wire:click.prevent="markedAsSolved">
                        <i class="fa fa-check-circle {{ $model->status == 0 ? 'text-red-400' : 'text-green-400' }} mt-1 mx-1" aria-hidden="true"></i>
                    </a>
                @else
                    <i class="fa fa-check-circle {{ $model->status == 0 ? 'text-red-400' : 'text-green-400' }} mt-1 mx-1" aria-hidden="true"></i>
                @endauth
            </div>
        </div>
    </section>
</div>
