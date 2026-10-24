import { z } from 'zod';

const configSchema = z.object({
    NODE_ENV: z.enum(['development', 'test', 'production'])
        .default('development'),
    PORT: z.coerce.number().int().min(1).max(65535).default(3001),
    LARAVEL_BASE_URL: z.string().url().default('http://127.0.0.1:8000'),
    GATEWAY_SHARED_SECRET: z.string()
        .min(32, 'GATEWAY_SHARED_SECRET must contain at least 32 characters'),
    WHATSAPP_SESSION_DIR: z.string()
        .min(1)
        .default('./storage/whatsapp-session'),
});

const result = configSchema.safeParse(process.env);

if (!result.success) {
    console.error('Invalid gateway configuration:');

    for (const issue of result.error.issues) {
        console.error(`- ${issue.path.join('.')}: ${issue.message}`);
    }

    process.exit(1);
}

export const config = {
    nodeEnv: result.data.NODE_ENV,
    port: result.data.PORT,
    laravelBaseUrl: result.data.LARAVEL_BASE_URL,
    gatewaySharedSecret: result.data.GATEWAY_SHARED_SECRET,
    whatsappSessionDir: result.data.WHATSAPP_SESSION_DIR,
};
