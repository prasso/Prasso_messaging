<div class="space-y-4">
    <div>
        <h4 class="text-sm font-medium text-gray-500">From</h4>
        <p class="text-gray-900">{{ $reply->from }}</p>
    </div>

    <div>
        <h4 class="text-sm font-medium text-gray-500">Received At</h4>
        <p class="text-gray-900">{{ $reply->received_at->format('M d, Y H:i:s') }}</p>
    </div>

    @if($reply->guest)
        <div>
            <h4 class="text-sm font-medium text-gray-500">Guest Name</h4>
            <p class="text-gray-900">{{ $reply->guest->name }}</p>
        </div>
    @endif

    <div>
        <h4 class="text-sm font-medium text-gray-500">Reply Message</h4>
        <div class="mt-2 p-4 bg-gray-50 rounded text-gray-900 whitespace-pre-wrap">
            {{ $reply->body }}
        </div>
    </div>

    @if($reply->media && count($reply->media) > 0)
        <div>
            <h4 class="text-sm font-medium text-gray-500">Attachments</h4>
            <ul class="mt-2 space-y-2">
                @foreach($reply->media as $url)
                    <li>
                        <a href="{{ $url }}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                            {{ basename($url) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
