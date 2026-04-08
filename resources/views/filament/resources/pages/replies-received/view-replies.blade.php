<x-filament::page>
    <div class="space-y-6">
        <!-- Message Details Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Subject</h3>
                    <p class="text-lg font-semibold text-gray-900">{{ $record->subject }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Message Type</h3>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium
                        @if($record->type === 'sms')
                            bg-blue-100 text-blue-800
                        @else
                            bg-green-100 text-green-800
                        @endif">
                        {{ strtoupper($record->type) }}
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Sent At</h3>
                    <p class="text-lg font-semibold text-gray-900">{{ $record->created_at->format('M d, Y H:i') }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Total Replies</h3>
                    <p class="text-lg font-semibold text-gray-900">{{ $record->inboundMessages->count() }}</p>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Message Body</h3>
                <p class="text-gray-700 whitespace-pre-wrap">{{ $record->body }}</p>
            </div>
        </div>

        <!-- Replies Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Replies Received</h3>
            </div>
            {{ $this->table }}
        </div>
    </div>
</x-filament::page>
