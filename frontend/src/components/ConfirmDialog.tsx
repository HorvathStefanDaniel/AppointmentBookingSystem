import { useEffect } from 'react'
import type { ReactNode } from 'react'
import { createPortal } from 'react-dom'

type ConfirmDialogProps = {
  open: boolean
  title: string
  message: ReactNode
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: () => void
  onCancel: () => void
  confirmDisabled?: boolean
}

export const ConfirmDialog = ({
  open,
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Go back',
  onConfirm,
  onCancel,
  confirmDisabled = false,
}: ConfirmDialogProps) => {
  useEffect(() => {
    if (open) {
      const previousOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      return () => {
        document.body.style.overflow = previousOverflow
      }
    }
  }, [open])

  if (!open) {
    return null
  }

  const dialog = (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-2xl">
        <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
        <div className="mt-3 text-sm text-slate-600">{message}</div>
        <div className="mt-6 flex justify-end gap-3">
          <button
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-100"
            onClick={onCancel}
          >
            {cancelLabel}
          </button>
          <button
            className="rounded bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-800"
            onClick={onConfirm}
            disabled={confirmDisabled}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )

  return createPortal(dialog, document.body)
}

