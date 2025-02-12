  <div class="body bg-white mt-2 rounded-md shadow-md flex flex-col">
      @foreach ($permissions as $key => $groupPermission)
      <div class="flex justify-start">
          <div class="w-56 border-r p-2 border-b flex flex-col sm:flex-row sm:justify-between">
              <span class="mb-4 sm:mb-0">{{ ucfirst($key) }}</span>
              <div class="flex flex-col sm::flex-row">
                  <button onclick="enableSubFeatures('{{ $key }}')" class="border-black border max-w-20 rounded-md p-2 bg-gray-100 text-xs mb-2" type="button">
                      Enable All
                  </button>
                  <button onclick="disableSubFeatures('{{ $key }}')" class="border-black border max-w-20 rounded-md p-2 bg-gray-100 text-xs" type="button">
                      Disable All
                  </button>
              </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 p-2 w-full border-b" id="{{ $key }}-sub">
              @foreach($groupPermission as $permission)
              <div class="grid grid-cols-4 border">
                  <div class="inline-block align-middle m-auto">
                    @if(Route::is('role.edit'))
                      <input type="checkbox" id="{{ $permission['id'] }}" name="permissionsId[]" value="{{ $permission['id'] }}" {{ $permission['checked'] ? 'checked' : '' }}>
                    @else
                        <input type="checkbox" id="{{ $permission['id'] }}" name="permissionsId[]" value="{{ $permission['id'] }}">
                    @endif
                  </div>
                  <div class="inline-block align-middle m-auto col-span-3">
                      <label for="{{$permission['id']}}">{{$permission['name']}}</label>
                  </div>
              </div>
              @endforeach

          </div>
      </div>

      @endforeach

      <script>
          function enableSubFeatures(id) {
              var subFeatures = document.getElementById(id + '-sub');
              var checkboxes = subFeatures.getElementsByTagName('input');

              for (var i = 0; i < checkboxes.length; i++) {
                  checkboxes[i].checked = true;
              }
          }

          function disableSubFeatures(id) {
              var subFeatures = document.getElementById(id + '-sub');
              var checkboxes = subFeatures.getElementsByTagName('input');

              for (var i = 0; i < checkboxes.length; i++) {
                  checkboxes[i].checked = false;
              }
          }
      </script>
  </div>