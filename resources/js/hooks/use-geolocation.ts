import { useCallback, useState } from 'react';

export type Coords = { latitude: number; longitude: number; accuracy: number };

type State = {
    coords: Coords | null;
    error: string | null;
    loading: boolean;
};

/**
 * Reads the device position on demand.
 *
 * Deliberately not automatic on mount: asking for location before the student has
 * chosen to check in trains them to dismiss the prompt.
 */
export function useGeolocation() {
    const [state, setState] = useState<State>({
        coords: null,
        error: null,
        loading: false,
    });

    const request = useCallback((): Promise<Coords | null> => {
        if (!('geolocation' in navigator)) {
            setState({
                coords: null,
                error: 'Perangkat ini tidak mendukung deteksi lokasi.',
                loading: false,
            });

            return Promise.resolve(null);
        }

        setState((previous) => ({ ...previous, loading: true, error: null }));

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const coords: Coords = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    };

                    setState({ coords, error: null, loading: false });
                    resolve(coords);
                },
                (error) => {
                    // Each case needs a different fix from the student, so they are
                    // not collapsed into one "location failed".
                    const message =
                        error.code === error.PERMISSION_DENIED
                            ? 'Akses lokasi ditolak. Izinkan lokasi di pengaturan browser, lalu coba lagi.'
                            : error.code === error.POSITION_UNAVAILABLE
                              ? 'Lokasi tidak tersedia. Pastikan GPS aktif.'
                              : 'Deteksi lokasi terlalu lama. Coba lagi di area terbuka.';

                    setState({ coords: null, error: message, loading: false });
                    resolve(null);
                },
                { enableHighAccuracy: true, timeout: 15_000, maximumAge: 0 },
            );
        });
    }, []);

    return { ...state, request };
}
