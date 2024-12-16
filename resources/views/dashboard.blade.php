   @if(Auth()->user()->role_id === \App\Models\Role::ADMIN)

   @elseif(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
   <div>
       @include('companies.index')
   </div>

   @elseif(Auth()->user()->role_id ==\App\Models\Role::AGENT)
   <div>
       @include('agents.index')
   </div>
   @endif