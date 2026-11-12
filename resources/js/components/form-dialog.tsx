import type { ReactNode } from 'react';

type FormDialogProps = {
    title: string;
    open: boolean;
    onClose: () => void;
    children: ReactNode;
};

export default function FormDialog({
    title,
    open,
    onClose,
    children,
}: FormDialogProps) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <section
                aria-labelledby="form-dialog-title"
                aria-modal="true"
                className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl border bg-background p-6 shadow-xl"
                role="dialog"
            >
                <header className="mb-5 flex items-center justify-between gap-4">
                    <h2 id="form-dialog-title" className="text-lg font-semibold">
                        {title}
                    </h2>
                    <button
                        aria-label="Close dialog"
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                        onClick={onClose}
                        type="button"
                    >
                        Close
                    </button>
                </header>
                {children}
            </section>
        </div>
    );
}
