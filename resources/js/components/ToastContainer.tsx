import { useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import Toast, { ToastType } from './Toast';

interface ToastMessage {
    id: string;
    message: string;
    type?: ToastType;
    duration?: number;
}

export function useToast() {
    const [toasts, setToasts] = useState<ToastMessage[]>([]);

    const showToast = useCallback((message: string, type: ToastType = 'info', duration = 5000) => {
        const id = Math.random().toString(36).substring(2, 9);
        setToasts((prev) => [...prev, { id, message, type, duration }]);
        return id;
    }, []);

    const removeToast = useCallback((id: string) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    }, []);

    return { showToast, removeToast, toasts };
}

interface ToastContainerProps {
    toasts: ToastMessage[];
    onRemove: (id: string) => void;
}

function ToastContainer({ toasts, onRemove }: ToastContainerProps) {
    return (
        <div className="fixed top-4 right-4 z-50 flex flex-col gap-3 pointer-events-none">
            <AnimatePresence mode="popLayout">
                {toasts.map((toast) => (
                    <motion.div
                        key={toast.id}
                        layout
                        initial={{ opacity: 0, y: -20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        className="pointer-events-auto"
                    >
                        <Toast
                            id={toast.id}
                            message={toast.message}
                            type={toast.type}
                            duration={toast.duration}
                            onClose={onRemove}
                        />
                    </motion.div>
                ))}
            </AnimatePresence>
        </div>
    );
}

export default ToastContainer;

