@extends('admin.layouts.admin')

@section('title', 'Broadcast Settings')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Broadcast Settings</h1>
                <p class="text-gray-600 mt-2">Configure real-time broadcasting for live updates</p>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full {{ $settings->isBroadcastingEnabled() ? 'bg-green-400 animate-pulse' : 'bg-gray-400' }}"></div>
                    <span class="text-sm font-medium {{ $settings->isBroadcastingEnabled() ? 'text-green-700' : 'text-gray-500' }}">
                        {{ $settings->isBroadcastingEnabled() ? 'Broadcasting Active' : 'Broadcasting Disabled' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Success Alert -->
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-green-700 font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-6">
                <h2 class="text-2xl font-bold text-white">Real-time Broadcasting Configuration</h2>
                <p class="text-blue-100 mt-2">Choose between Laravel Reverb (free, self-hosted) or Pusher (cloud service)</p>
            </div>

            <form method="POST" action="{{ route('admin.settings.broadcast.update') }}" class="p-8">
                @csrf

                <!-- Basic Settings -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Enable Broadcasting -->
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex items-center h-6">
                                <input type="checkbox" id="enabled" name="enabled" value="1"
                                       {{ $settings->enabled ? 'checked' : '' }}
                                       class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                            </div>
                            <div class="flex-1">
                                <label for="enabled" class="text-lg font-semibold text-gray-900 cursor-pointer">
                                    Enable Real-time Broadcasting
                                </label>
                                <p class="text-sm text-gray-600 mt-1">
                                    Turn on live updates for source processing and chat messages
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Broadcasting Driver -->
                    <div class="space-y-4">
                        <label for="driver" class="block text-lg font-semibold text-gray-900">
                            Broadcasting Driver
                        </label>
                        <select id="driver" name="driver" required
                                class="w-full px-4 py-3 text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('driver') border-red-500 ring-red-500 @enderror">
                            <option value="log" {{ $settings->driver === 'log' ? 'selected' : '' }}>
                                🚫 Log (Disabled)
                            </option>
                            <option value="reverb" {{ $settings->driver === 'reverb' ? 'selected' : '' }}>
                                🚀 Laravel Reverb (Recommended)
                            </option>
                            <option value="pusher" {{ $settings->driver === 'pusher' ? 'selected' : '' }}>
                                ☁️ Pusher (Cloud Service)
                            </option>
                        </select>
                        @error('driver')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Pusher Settings -->
                <div id="pusher-settings" class="bg-gradient-to-br from-orange-50 to-red-50 border border-orange-200 rounded-2xl p-6 mb-8 transition-all duration-300" style="display: none;">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5.5 16a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 16h-8z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Pusher Configuration</h3>
                            <p class="text-sm text-gray-600">
                                Get your credentials from <a href="https://pusher.com" target="_blank" class="text-orange-600 hover:text-orange-700 font-medium underline">pusher.com</a>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label for="pusher_app_id" class="block text-sm font-semibold text-gray-700">App ID</label>
                            <input type="text" id="pusher_app_id" name="pusher_app_id"
                                   value="{{ old('pusher_app_id', $settings->pusher_app_id) }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors @error('pusher_app_id') border-red-500 ring-red-500 @enderror">
                            @error('pusher_app_id')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="pusher_key" class="block text-sm font-semibold text-gray-700">Key</label>
                            <input type="text" id="pusher_key" name="pusher_key"
                                   value="{{ old('pusher_key', $settings->pusher_key) }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors @error('pusher_key') border-red-500 ring-red-500 @enderror">
                            @error('pusher_key')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="pusher_secret" class="block text-sm font-semibold text-gray-700">Secret</label>
                            <input type="password" id="pusher_secret" name="pusher_secret"
                                   value="{{ old('pusher_secret', $settings->pusher_secret) }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors @error('pusher_secret') border-red-500 ring-red-500 @enderror">
                            @error('pusher_secret')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="pusher_cluster" class="block text-sm font-semibold text-gray-700">Cluster</label>
                            <input type="text" id="pusher_cluster" name="pusher_cluster"
                                   value="{{ old('pusher_cluster', $settings->pusher_cluster) }}"
                                   placeholder="mt1"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors @error('pusher_cluster') border-red-500 ring-red-500 @enderror">
                            @error('pusher_cluster')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Reverb Settings -->
                <div id="reverb-settings" class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8 transition-all duration-300" style="display: none;">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Laravel Reverb Configuration</h3>
                            <p class="text-sm text-gray-600">
                                Free, self-hosted WebSocket server. Run <code class="bg-gray-200 px-2 py-1 rounded text-xs">php artisan reverb:start</code>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label for="reverb_app_id" class="block text-sm font-semibold text-gray-700">App ID</label>
                            <input type="text" id="reverb_app_id" name="reverb_app_id"
                                   value="{{ old('reverb_app_id', $settings->reverb_app_id) }}"
                                   placeholder="my-app-id"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors @error('reverb_app_id') border-red-500 ring-red-500 @enderror">
                            @error('reverb_app_id')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="reverb_key" class="block text-sm font-semibold text-gray-700">Key</label>
                            <input type="text" id="reverb_key" name="reverb_key"
                                   value="{{ old('reverb_key', $settings->reverb_key) }}"
                                   placeholder="my-app-key"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors @error('reverb_key') border-red-500 ring-red-500 @enderror">
                            @error('reverb_key')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="reverb_secret" class="block text-sm font-semibold text-gray-700">Secret</label>
                            <input type="password" id="reverb_secret" name="reverb_secret"
                                   value="{{ old('reverb_secret', $settings->reverb_secret) }}"
                                   placeholder="my-app-secret"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors @error('reverb_secret') border-red-500 ring-red-500 @enderror">
                            @error('reverb_secret')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="reverb_host" class="block text-sm font-semibold text-gray-700">Host</label>
                            <input type="text" id="reverb_host" name="reverb_host"
                                   value="{{ old('reverb_host', $settings->reverb_host) }}"
                                   placeholder="localhost"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors @error('reverb_host') border-red-500 ring-red-500 @enderror">
                            @error('reverb_host')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="reverb_port" class="block text-sm font-semibold text-gray-700">Port</label>
                            <input type="number" id="reverb_port" name="reverb_port"
                                   value="{{ old('reverb_port', $settings->reverb_port) }}"
                                   placeholder="8080" min="1" max="65535"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors @error('reverb_port') border-red-500 ring-red-500 @enderror">
                            @error('reverb_port')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="reverb_scheme" class="block text-sm font-semibold text-gray-700">Scheme</label>
                            <select id="reverb_scheme" name="reverb_scheme"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors @error('reverb_scheme') border-red-500 ring-red-500 @enderror">
                                <option value="http" {{ $settings->reverb_scheme === 'http' ? 'selected' : '' }}>HTTP</option>
                                <option value="https" {{ $settings->reverb_scheme === 'https' ? 'selected' : '' }}>HTTPS</option>
                            </select>
                            @error('reverb_scheme')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.dashboard') }}"
                       class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition-colors font-medium">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                        </svg>
                        Back to Dashboard
                    </a>

                    <button type="submit"
                            class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:from-blue-700 hover:to-purple-700 transition-colors font-medium shadow-lg hover:shadow-xl">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z"/>
                        </svg>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden mt-8">
            <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-8 py-6">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">Getting Started Guide</h3>
                </div>
            </div>

            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Reverb Setup -->
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900">Laravel Reverb (Recommended)</h4>
                        </div>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-medium">1</span>
                                <span>Install Reverb: <code class="bg-gray-100 px-2 py-1 rounded text-xs">composer require laravel/reverb</code></span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-medium">2</span>
                                <span>Publish config: <code class="bg-gray-100 px-2 py-1 rounded text-xs">php artisan reverb:install</code></span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-medium">3</span>
                                <span>Configure settings above</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-medium">4</span>
                                <span>Start server: <code class="bg-gray-100 px-2 py-1 rounded text-xs">php artisan reverb:start</code></span>
                            </div>
                        </div>
                    </div>

                    <!-- Pusher Setup -->
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M5.5 16a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 16h-8z"/>
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900">Pusher (Cloud Service)</h4>
                        </div>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs font-medium">1</span>
                                <span>Create account at <a href="https://pusher.com" target="_blank" class="text-orange-600 hover:text-orange-700 underline">pusher.com</a></span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs font-medium">2</span>
                                <span>Create new app in dashboard</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs font-medium">3</span>
                                <span>Copy credentials to form above</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs font-medium">4</span>
                                <span>Install: <code class="bg-gray-100 px-2 py-1 rounded text-xs">composer require pusher/pusher-php-server</code></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const driverSelect = document.getElementById('driver');
    const pusherSettings = document.getElementById('pusher-settings');
    const reverbSettings = document.getElementById('reverb-settings');

    function toggleSettings() {
        const driver = driverSelect.value;

        pusherSettings.style.display = driver === 'pusher' ? 'block' : 'none';
        reverbSettings.style.display = driver === 'reverb' ? 'block' : 'none';
    }

    driverSelect.addEventListener('change', toggleSettings);
    toggleSettings(); // Initial setup
});
</script>
@endsection