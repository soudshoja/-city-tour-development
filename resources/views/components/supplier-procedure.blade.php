@if($procedure && $procedure->procedure)
    <section class="page-break-inside-avoid card p-5">
        <div class="card-inner p-5">
            <div class="prose prose-sm max-w-none text-gray-800">
                {!! $procedure->procedure !!}
            </div>
        </div>
    </section>
@endif