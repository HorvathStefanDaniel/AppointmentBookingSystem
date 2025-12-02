import axios from 'axios'

export const getErrorMessage = (error: unknown, fallback = 'Something went wrong') => {
  if (axios.isAxiosError(error)) {
    const message = (error.response?.data as { message?: string })?.message
    return message ?? fallback
  }
  if (error instanceof Error) {
    return error.message
  }
  return fallback
}

