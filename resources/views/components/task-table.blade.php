<div>
    <h3>Task List</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Description</th>
                <th>Agent</th>
                <th>Client</th>
                <th>Supplier</th>
                <th>Price</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tasks as $task)
                <tr>
                    <td>{{ $task->id }}</td>
                    <td>{{ $task->description }}</td>
                    <td>{{ $task->agentName }}</td>
                    <td>{{ $task->clientName }}</td>
                    <td>{{ $task->supplierName }}</td>
                    <td>{{ $task->price }}</td>
                    <td>{{ $task->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
