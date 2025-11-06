import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, AlertCircle, CheckCircle2, AlertTriangle, Info } from 'lucide-react';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastProps {
    id: string;
    message: string;
    type?: ToastType;
    duration?: number;
    onClose: (id: string) => void;
}

export default function Toast({ id, message, type = 'info', duration = 5000, onClose }: ToastProps) {
    const [isVisible, setIsVisible] = useState(true);

    useEffect(() => {
        if (duration > 0) {
            const timer = setTimeout(() => {
                setIsVisible(false);
                setTimeout(() => onClose(id), 300); // Wait for exit animation
            }, duration);

            return () => clearTimeout(timer);
        }
    }, [duration, id, onClose]);

    const handleClose = () => {
        setIsVisible(false);
        setTimeout(() => onClose(id), 300);
    };

    const icons = {
        success: CheckCircle2,
        error: AlertCircle,
        warning: AlertTriangle,
        info: Info,
    };

    const colors = {
        success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        error: 'bg-rose-50 border-rose-200 text-rose-800',
        warning: 'bg-amber-50 border-amber-200 text-amber-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800',
    };

    const iconColors = {
        success: 'text-emerald-600',
        error: 'text-rose-600',
        warning: 'text-amber-600',
        info: 'text-blue-600',
    };

    const Icon = icons[type];

    return (
        <AnimatePresence>
            {isVisible && (
                <motion.div
                    initial={{ opacity: 0, y: -20, scale: 0.95 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: -10, scale: 0.95 }}
                    transition={{ duration: 0.2 }}
                    className={`flex items-center gap-3 px-4 py-3 rounded-lg border shadow-lg max-w-md ${colors[type]}`}
                >
                    <Icon className={`w-5 h-5 flex-shrink-0 ${iconColors[type]}`} />
                    <p className="flex-1 text-sm font-medium">{message}</p>
                    <button
                        onClick={handleClose}
                        className="p-1 hover:opacity-70 transition-opacity"
                        aria-label="Close notification"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </motion.div>
            )}
        </AnimatePresence>
    );
}

