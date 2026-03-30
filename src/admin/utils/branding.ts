type BrandingAssets = {
    logo?: string;
    animatedLogo?: string;
};

type RuntimeWithBranding = {
    brandingAssets?: BrandingAssets;
};

function getRuntimeBranding(): RuntimeWithBranding {
    return (
        (window as any).aosaiData ||
        (window as any).aosaiPortalData ||
        {}
    ) as RuntimeWithBranding;
}

export function getBrandingAssets(): BrandingAssets {
    return getRuntimeBranding().brandingAssets || {};
}

export function getBrandLogo(fallback?: string): string | undefined {
    return getBrandingAssets().logo || fallback;
}

export function getAnimatedBrandLogo(fallback?: string): string | undefined {
    return getBrandingAssets().animatedLogo || getBrandingAssets().logo || fallback;
}
