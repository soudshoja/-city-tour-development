<x-app-layout>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">

                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Send WhatsApp Message via Resayil</h4>
                    </div>

                    <div class="card-body p-4">

                        <form action="{{ route('whatsapp.sendToResayilSimple') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="client_id" class="form-label">Select Client</label>
                                <select name="client_id" id="client_id" class="form-select" required>
                                    <option value="">-- Select Client --</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}">
                                            {{ $client->name }} ({{ $client->phone }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Message to Send</label>
                                <textarea name="message" id="message" rows="4" class="form-control p-2 w-full rounded-md shadow-sm "
                                    placeholder="Enter your message here..." required></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    Send Message
                                </button>
                            </div>

                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
