const memoryCache = new Map()
const pending = new Map()


export async function getWaveform(
  file,
  bars = 220
) {
  if (!file) {
    return []
  }

  const key =
    `${file}::${bars}`


  if (memoryCache.has(key)) {
    return memoryCache.get(key)
  }


  if (pending.has(key)) {
    return pending.get(key)
  }


  const promise = (async () => {
    const params =
      new URLSearchParams({
        file,
        bars: String(bars)
      })


    const response = await fetch(
      `/castillo-api/waveform.php?${params.toString()}`,
      {
        cache: 'no-store'
      }
    )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible obtener el waveform.'
      )
    }


    const result =
      Array.isArray(data.bars)
        ? data.bars
        : []


    memoryCache.set(
      key,
      result
    )


    return result
  })()


  pending.set(
    key,
    promise
  )


  try {
    return await promise
  } finally {
    pending.delete(key)
  }
}