const runtimeData = (window as any).aosaiData || (window as any).aosaiPortalData || {};
const API_BASE = (runtimeData.apiBase || '/wp-json').replace(/\/$/, '');

function normalizeApiPayload<T>(payload: any): T {
    if (
        payload
        && typeof payload === 'object'
        && !Array.isArray(payload)
        && Object.prototype.hasOwnProperty.call(payload, 'data')
        && Object.keys(payload).length === 1
    ) {
        return payload.data as T;
    }

    return payload as T;
}

export async function apiGet<T = any>(endpoint: string, params?: Record<string, any>): Promise<T> {
    const url = new URL(API_BASE + endpoint, window.location.origin);
    if (params) {
        Object.entries(params).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v));
        });
    }
    const res = await fetch(url.toString(), {
        headers: { 'X-WP-Nonce': runtimeData.nonce || '' },
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message || `HTTP ${res.status}`);
    }
    return normalizeApiPayload<T>(await res.json());
}

export async function apiPost<T = any>(endpoint: string, data?: any): Promise<T> {
    const res = await fetch(API_BASE + endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': runtimeData.nonce || '',
        },
        body: data ? JSON.stringify(data) : undefined,
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message || `HTTP ${res.status}`);
    }
    return normalizeApiPayload<T>(await res.json());
}

export async function apiPut<T = any>(endpoint: string, data?: any): Promise<T> {
    const res = await fetch(API_BASE + endpoint, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': runtimeData.nonce || '',
        },
        body: data ? JSON.stringify(data) : undefined,
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message || `HTTP ${res.status}`);
    }
    return normalizeApiPayload<T>(await res.json());
}

export async function apiDelete<T = any>(endpoint: string): Promise<T> {
    const res = await fetch(API_BASE + endpoint, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': runtimeData.nonce || '',
        },
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message || `HTTP ${res.status}`);
    }
    return normalizeApiPayload<T>(await res.json());
}

export async function apiPostForm<T = any>(endpoint: string, formData: FormData): Promise<T> {
    const res = await fetch(API_BASE + endpoint, {
        method: 'POST',
        headers: {
            'X-WP-Nonce': runtimeData.nonce || '',
        },
        body: formData,
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message || `HTTP ${res.status}`);
    }
    return normalizeApiPayload<T>(await res.json());
}

