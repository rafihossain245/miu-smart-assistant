import React, { createContext, useContext, useEffect, useState } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const EchoContext = createContext();

export const useEcho = () => {
    const context = useContext(EchoContext);
    if (!context) {
        throw new Error('useEcho must be used within an EchoProvider');
    }
    return context;
};

export const EchoProvider = ({ children }) => {
    const [echo, setEcho] = useState(null);
    const [isConnected, setIsConnected] = useState(false);

    useEffect(() => {
        // Check if broadcasting is enabled (you might want to get this from a server endpoint)
        const initializeEcho = async () => {
            try {
                // Get broadcast settings from server
                const response = await fetch('/api/broadcast-config');
                if (!response.ok) {
                    console.log('Broadcasting not configured');
                    return;
                }

                const config = await response.json();

                if (!config.enabled) {
                    console.log('Broadcasting disabled');
                    return;
                }

                let echoConfig = {
                    broadcaster: 'pusher',
                    key: config.key,
                    wsHost: config.host || window.location.hostname,
                    wsPort: config.port || 6001,
                    wssPort: config.port || 6001,
                    forceTLS: config.scheme === 'https',
                    encrypted: config.scheme === 'https',
                    disableStats: true,
                    enabledTransports: ['ws', 'wss'],
                    cluster: config.cluster,
                    auth: {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                    },
                };

                // Configure based on driver
                if (config.driver === 'reverb') {
                    window.Pusher = Pusher;
                    echoConfig = {
                        ...echoConfig,
                        broadcaster: 'reverb',
                        // key: config.key,
                        // wsHost: config.host || window.location.hostname,
                        // wsPort: config.port || 8080,
                        // wssPort: config.port || 8080,
                        // forceTLS: config.scheme === 'https',
                        key: import.meta.env.VITE_REVERB_APP_KEY,
                        wsHost: import.meta.env.VITE_REVERB_HOST,
                        wsPort: import.meta.env.VITE_REVERB_PORT,
                        wssPort: import.meta.env.VITE_REVERB_PORT,
                        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'wss',
                        enabledTransports: ['ws', 'wss'],
                    };
                } else if (config.driver === 'pusher') {
                    window.Pusher = Pusher;
                    echoConfig = {
                        ...echoConfig,
                        broadcaster: 'pusher',
                        key: config.key,
                        cluster: config.cluster,
                        forceTLS: true,
                    };
                }

                const echoInstance = new Echo(echoConfig);

                echoInstance.connector.pusher.connection.bind('connected', () => {
                    console.log('Echo connected');
                    setIsConnected(true);
                });

                echoInstance.connector.pusher.connection.bind('disconnected', () => {
                    console.log('Echo disconnected');
                    setIsConnected(false);
                });

                echoInstance.connector.pusher.connection.bind('error', (error) => {
                    console.error('Echo connection error:', error);
                    setIsConnected(false);
                });

                setEcho(echoInstance);

            } catch (error) {
                console.error('Failed to initialize Echo:', error);
            }
        };

        initializeEcho();

        return () => {
            if (echo) {
                echo.disconnect();
            }
        };
    }, []);

    return (
        <EchoContext.Provider value={{ echo, isConnected }}>
            {children}
        </EchoContext.Provider>
    );
};