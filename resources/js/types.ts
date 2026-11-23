export interface Generation {
  id: string;
  provider_id: string | null;
  prompt: string;
  size: string;
  quality: string;
  reference_images: string[];
  status: 'queued' | 'submitting' | 'processing' | 'succeeded' | 'failed' | 'attention';
  estimated_provider_cost: string;
  estimated_customer_price: string;
  maximum_customer_price: string;
  actual_provider_cost: string | null;
  actual_customer_price: string | null;
  image_url: string | null;
  image_url_expires_at: string | null;
  error_message: string | null;
  error_code: string | null;
  created_at: string;
  completed_at: string | null;
  usage: Record<string, number> | null;
  rates: Record<string, string> | null;
}
export function money(value: string | null | undefined, places = 6): string {
  if (value === null || value === undefined) return '—';
  const [whole, fraction = ''] = String(value).split('.');
  const padded = fraction.padEnd(places + 1, '0');
  const rounded = BigInt(whole + padded.slice(0, places)) + (padded[places] >= '5' ? 1n : 0n);
  const digits = rounded.toString().padStart(places + 1, '0');
  return '$' + digits.slice(0, -places) + '.' + digits.slice(-places);
}
export function date(value: string): string {
  return new Date(value).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}
export function active(status: string): boolean {
  return !['succeeded', 'failed', 'attention'].includes(status);
}
