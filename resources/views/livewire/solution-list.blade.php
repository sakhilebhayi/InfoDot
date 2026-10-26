<div>
    @foreach($solutions as $solution)
        <div class="mx-auto sm:px-3 lg:px-4 my-6">
            <div class="w-full px-4">
                <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="flex flex-col sm:flex-row rounded-3xl border border-[#434656]/35 bg-[rgba(49,57,77,0.55)] shadow-[0_12px_32px_rgba(0,0,0,0.28)] overflow-hidden transition hover:-translate-y-0.5 hover:border-[#8d90a2]/50" style="backdrop-filter:blur(20px);">
                    <div class="shrink-0 sm:w-56">
                        <img src="https://source.unsplash.com/random/300x200?productivity,business" alt="" class="h-48 w-full object-cover sm:h-full">
                        <div class="grid grid-cols-2 bg-[#131b2e] text-[#b7c8e1] text-sm">
                            <span class="flex justify-center items-center gap-1 py-3">
                                <i class="fas fa-clock"></i>
                                {{ $solution->duration }} {{ $solution->duration_type }}
                            </span>
                            <span class="flex justify-center items-center gap-1 py-3">
                                <i class="fas fa-shoe-prints"></i> {{ $solution->steps }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col justify-between p-6">
                        <div>
                            <h2 class="text-xl font-bold capitalize text-[#dae2fd] mb-3" style="font-family:'Manrope',sans-serif;">
                                {{$solution->solution_title ?? ''}}
                            </h2>
                            <p class="text-sm leading-7 text-[#c3c5d8] line-clamp-3">
                                {{ $solution->solution_description ?? '' }}
                            </p>
                        </div>
                        <div class="mt-5 flex items-center gap-6 text-sm text-[#b7c8e1]">
                            <span><i class="fa fa-eye mr-1"></i> 3</span>
                            <span><i class="fa fa-comment mr-1"></i> {{ $solution->comments()->count() }}</span>
                            <span><i class="fa fa-bar-chart mr-1"></i> 10</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    @endforeach
    <div x-data="{
            observe () {
                let observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            @this.call('loadMore')
                        }
                    })
                }, {
                    root: null
                })

                observer.observe(this.$el)
            }
        }"
        x-init="observe"></div>
        <div class="w-full">
            @if($solutions->hasMorePages())
                <button wire:click.prevent="loadMore" class="px-6 py-2.5 rounded-full border border-[#434656]/40 bg-[#131b2e] text-[#b7c8e1] text-sm font-semibold flex justify-center items-center hover:bg-[#1a2438] hover:text-[#b6c4ff] transition">Load More</button>
            @endif
        </div>
</div>
