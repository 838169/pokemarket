const endpoints = [
    'http://localhost:8001/pokemon-api/v1/health',
    'http://localhost:8002/trainer-api/v1/health',
];
for (const endpoint of endpoints) {
    let ready = false;
    for (let attempt = 0; attempt < 20; attempt++) {
        try {
            const response = await fetch(endpoint, { signal: AbortSignal.timeout(1000) });
            ready = response.ok && (await response.json()).status === 'ready';
        } catch (_) {}
        if (ready) break;
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    if (!ready) throw new Error(`Servicio no disponible: ${endpoint}`);
    console.log(`READY ${endpoint}`);
}
