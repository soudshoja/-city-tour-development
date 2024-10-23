<x-app-layout>
    <div class="permission">
        <div class="header flex justify-between bg-white rounded-md p-2 shadow-md">
            <h1 class="">Permission For Admin</h1>
            <div class="action">
                <a href="{{ route('role.index') }}" class="btn btn-primary">Back</a>
            </div>
        </div>
        <div class="body bg-white p-2 mt-2 rounded-md shadow-md">

            @csrf
            @foreach ($permissions as $feature => $subFeatures)
            <div class="border-b-2">
                <div class="p-2 grid grid-cols-8 ">
                    <div class="col-span-2">
                        <span>{{$feature}}</span>
                        <button id="{{$feature}}-main" @click="toggleSubFeatures('{{$feature}}')" class="btn btn-primary">
                            Toggle All
                        </button>
                    </div>
                    <div class="col-span-6 grid grid-cols-3 gap-2">
                        @foreach($subFeatures as $subFeature)
                        <div class="flex justify-evenly border">
                            <div class="">
                                <input type="checkbox" name="permissions[]" value="{{$subFeature}}" id="{{$subFeature}}">
                            </div>
                            <div class="">
                                <label for="{{$subFeature}}">{{$subFeature}}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
            <
                <script>
                function toggleDetails(feature) {
                var detailsRow = document.getElementById(feature + '-details');
                if (detailsRow.style.display === 'none') {
                detailsRow.style.display = '';
                } else {
                detailsRow.style.display = 'none';
                }
                }

                function toggleSubFeatures(feature) {
                var mainCheckbox = document.getElementById(feature + '-main');
                var subCheckboxes = document.querySelectorAll('#' + feature + '-details input[type="checkbox"]');
                subCheckboxes.forEach(function(checkbox) {
                checkbox.checked = mainCheckbox.checked;
                });
                }
                </script>

        </div>
        <div class="footer flex justify-evenly">
            <div x-data={role-modal:false} id="add-role">
                <div>
                    <button @click="role-modal=true" class="btn btn-primary">Add Role</button>
                    <div x-show="role-modal" class="modal">
                        <div class="modal-content">
                            <span @click="role-modal=false" class="close">&times;</span>
                            <h2>Choose Client and Set The Role Name</h2>
                            <form method="POST" action="">
                                @csrf
                                <div class="form-group">
                                    <label for="client">Client</label>
                                    <select name="client" id="client" class="form-control">
                                        @foreach($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="role">Role Name</label>
                                    <input type="text" name="role_name" id="role_name" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-primary">Assign Role</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>