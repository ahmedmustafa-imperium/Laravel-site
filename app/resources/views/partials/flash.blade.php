@if(session('success') || $errors->any())
    <div class="flash-wrap container" data-flash>
        @if(session('success'))<div class="flash flash--success"><span>✓</span><p>{{ session('success') }}</p><button type="button" data-flash-close>×</button></div>@endif
        @if($errors->any())<div class="flash flash--error"><span>!</span><div><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div><button type="button" data-flash-close>×</button></div>@endif
    </div>
@endif
