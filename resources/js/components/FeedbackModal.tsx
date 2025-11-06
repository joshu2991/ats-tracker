import { useState, FormEvent, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Star, Send, Loader2 } from 'lucide-react';
import { useForm, router } from '@inertiajs/react';

interface FeedbackModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export default function FeedbackModal({ isOpen, onClose }: FeedbackModalProps) {
    const [rating, setRating] = useState<number>(0);
    const [hoveredRating, setHoveredRating] = useState<number>(0);
    const [success, setSuccess] = useState<boolean>(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        rating: 0,
        description: '',
    });

    // Sync rating state with form data
    useEffect(() => {
        setRating(data.rating);
    }, [data.rating]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (data.rating === 0) {
            return;
        }

        post('/feedback', {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setSuccess(true);
                setTimeout(() => {
                    onClose();
                    resetForm();
                    // Don't reload - just close modal and stay on page
                    // The success message is handled by Inertia's flash messages
                }, 1500);
            },
            onError: () => {
                // Errors will be displayed via the errors object
            },
        });
    };

    const resetForm = () => {
        setRating(0);
        setHoveredRating(0);
        setSuccess(false);
        reset();
    };

    const handleClose = () => {
        if (!processing) {
            onClose();
            resetForm();
        }
    };

    const handleRatingChange = (newRating: number) => {
        setData('rating', newRating);
    };

    const handleDescriptionChange = (value: string) => {
        setData('description', value);
    };

    return (
        <AnimatePresence>
            {isOpen && (
                <>
                    {/* Backdrop */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={handleClose}
                        className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60]"
                    />

                    {/* Modal */}
                    <motion.div
                        initial={{ opacity: 0, scale: 0.95, y: 20 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.95, y: 20 }}
                        className="fixed inset-0 z-[70] flex items-center justify-center p-4 pointer-events-none"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative pointer-events-auto">
                            {/* Close Button */}
                            <button
                                onClick={handleClose}
                                disabled={processing}
                                className="absolute top-4 right-4 p-2 text-slate-400 hover:text-slate-600 transition-colors disabled:opacity-50"
                            >
                                <X className="w-5 h-5" />
                            </button>

                            {success ? (
                                /* Success State */
                                <div className="text-center py-8">
                                    <motion.div
                                        initial={{ scale: 0 }}
                                        animate={{ scale: 1 }}
                                        transition={{ type: 'spring', stiffness: 200 }}
                                        className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4"
                                    >
                                        <Send className="w-8 h-8 text-emerald-600" />
                                    </motion.div>
                                    <h3 className="text-xl font-bold text-slate-900 mb-2">Thank You!</h3>
                                    <p className="text-slate-600">Your feedback has been submitted successfully.</p>
                                </div>
                            ) : (
                                /* Form */
                                <>
                                    <h3 className="text-2xl font-bold text-slate-900 mb-2">Share Your Feedback</h3>
                                    <p className="text-slate-600 mb-6">Help us improve by rating your experience.</p>

                                    <form onSubmit={handleSubmit} className="space-y-6">
                                        {/* Star Rating */}
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-3">
                                                Rating <span className="text-red-500">*</span>
                                            </label>
                                            <div className="flex items-center gap-2">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <button
                                                        key={star}
                                                        type="button"
                                                        onClick={() => handleRatingChange(star)}
                                                        onMouseEnter={() => setHoveredRating(star)}
                                                        onMouseLeave={() => setHoveredRating(0)}
                                                        disabled={processing}
                                                        className="p-1 transition-transform hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed"
                                                    >
                                                        <Star
                                                            className={`w-8 h-8 ${
                                                                (hoveredRating || data.rating) >= star
                                                                    ? 'fill-amber-400 text-amber-400'
                                                                    : 'text-slate-300'
                                                            } transition-colors`}
                                                        />
                                                    </button>
                                                ))}
                                            </div>
                                            {errors.rating && (
                                                <p className="mt-2 text-sm text-red-600">{errors.rating}</p>
                                            )}
                                            {data.rating > 0 && (
                                                <p className="mt-2 text-sm text-slate-600">
                                                    {data.rating === 1 && 'Poor'}
                                                    {data.rating === 2 && 'Fair'}
                                                    {data.rating === 3 && 'Good'}
                                                    {data.rating === 4 && 'Very Good'}
                                                    {data.rating === 5 && 'Excellent'}
                                                </p>
                                            )}
                                        </div>

                                        {/* Description */}
                                        <div>
                                            <label htmlFor="description" className="block text-sm font-medium text-slate-700 mb-2">
                                                Description (Optional)
                                            </label>
                                            <textarea
                                                id="description"
                                                value={data.description}
                                                onChange={(e) => handleDescriptionChange(e.target.value)}
                                                disabled={processing}
                                                rows={4}
                                                maxLength={1000}
                                                className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none disabled:opacity-50 disabled:cursor-not-allowed"
                                                placeholder="Tell us more about your experience..."
                                            />
                                            <p className="mt-1 text-xs text-slate-500 text-right">
                                                {data.description.length}/1000
                                            </p>
                                            {errors.description && (
                                                <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                                            )}
                                        </div>

                                        {/* Submit Button */}
                                        <div className="flex gap-3">
                                            <button
                                                type="button"
                                                onClick={handleClose}
                                                disabled={processing}
                                                className="flex-1 px-4 py-3 border border-slate-300 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                disabled={processing || data.rating === 0}
                                                className="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                            >
                                                {processing ? (
                                                    <>
                                                        <Loader2 className="w-4 h-4 animate-spin" />
                                                        Submitting...
                                                    </>
                                                ) : (
                                                    <>
                                                        <Send className="w-4 h-4" />
                                                        Submit
                                                    </>
                                                )}
                                            </button>
                                        </div>
                                    </form>
                                </>
                            )}
                        </div>
                    </motion.div>
                </>
            )}
        </AnimatePresence>
    );
}

