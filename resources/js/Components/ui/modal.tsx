import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';

type ModalProps = {
    open: boolean;
    title: string;
    onClose: () => void;
    children: ReactNode;
    width?: number;
};

export default function Modal({ open, title, onClose, children, width = 520 }: ModalProps) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="modal-backdrop" onMouseDown={onClose}>
            <div className="modal" role="dialog" aria-modal="true" aria-label={title} style={{ maxWidth: width }} onMouseDown={(e) => e.stopPropagation()}>
                <div className="modal-head">
                    <h2>{title}</h2>
                    <button type="button" className="modal-close" onClick={onClose} aria-label="Close">
                        <X size={18} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
