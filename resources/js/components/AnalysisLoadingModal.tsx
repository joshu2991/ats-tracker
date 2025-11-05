import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Brain, Circle, Loader2, CheckCircle2, Clock, Lightbulb } from 'lucide-react';

interface AnalysisLoadingModalProps {
    isOpen: boolean;
}

const steps = [
    { id: 1, label: 'Extracting text from PDF', duration: 2000 },
    { id: 2, label: 'Analyzing format and structure', duration: 3000 },
    { id: 3, label: 'Checking keyword density', duration: 3000 },
    { id: 4, label: 'Running AI analysis', duration: 7000 },
    { id: 5, label: 'Generating personalized suggestions', duration: 3000 },
];

const funFacts = [
    '75% of resumes are rejected by ATS before a human sees them',
    'Recruiters spend an average of 6 seconds reviewing each resume',
    'Resumes with quantifiable achievements get 40% more interviews',
    'ATS systems struggle with tables, images, and complex formatting',
];

export default function AnalysisLoadingModal({ isOpen }: AnalysisLoadingModalProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [progress, setProgress] = useState(0);
    const [currentTip, setCurrentTip] = useState(0);

    useEffect(() => {
        if (!isOpen) {
            setCurrentStep(0);
            setProgress(0);
            return;
        }

        let accumulatedTime = 0;
        const totalDuration = steps.reduce((sum, step) => sum + step.duration, 0);

        steps.forEach((step, index) => {
            setTimeout(() => {
                setCurrentStep(index + 1);
                accumulatedTime += step.duration;
                setProgress((accumulatedTime / totalDuration) * 100);
            }, accumulatedTime);
        });

        // Rotate tips every 5 seconds
        const tipInterval = setInterval(() => {
            setCurrentTip((prev) => (prev + 1) % funFacts.length);
        }, 5000);

        return () => {
            clearInterval(tipInterval);
        };
    }, [isOpen]);

    const getStepState = (stepIndex: number): 'pending' | 'active' | 'complete' => {
        if (stepIndex < currentStep) {
            return 'complete';
        }
        if (stepIndex === currentStep - 1) {
            return 'active';
        }
        return 'pending';
    };

    return (
        <AnimatePresence>
            {isOpen && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md"
                >
                    <motion.div
                        initial={{ scale: 0.95, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        exit={{ scale: 0.95, opacity: 0 }}
                        transition={{ duration: 0.3, ease: 'easeOut' }}
                        className="relative w-full max-w-[480px] mx-4 bg-white rounded-[24px] p-[48px_40px] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)]"
                    >
                        {/* Animated Icon */}
                        <div className="flex items-center justify-center mb-6">
                            <motion.div
                                animate={{ rotate: 360, scale: [1, 1.1, 1] }}
                                transition={{
                                    rotate: { duration: 3, repeat: Infinity, ease: 'linear' },
                                    scale: { duration: 1.5, repeat: Infinity, ease: 'easeInOut' },
                                }}
                            >
                                <Brain className="w-16 h-16 text-indigo-600" />
                            </motion.div>
                        </div>

                        {/* Heading */}
                        <h2 className="text-2xl font-bold text-slate-900 text-center mb-3">
                            Analyzing Your Resume
                        </h2>

                        {/* Subtext */}
                        <p className="text-base text-slate-600 text-center mb-8">
                            Our AI is reviewing format, keywords, and content quality
                        </p>

                        {/* Progress Steps */}
                        <div className="flex flex-col gap-4 mb-8">
                            {steps.map((step, index) => {
                                const state = getStepState(index);
                                return (
                                    <motion.div
                                        key={step.id}
                                        initial={{ opacity: 0, x: -20 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        transition={{ delay: index * 0.1 }}
                                        className="flex items-center gap-4 transition-all duration-300"
                                    >
                                        {state === 'pending' && (
                                            <Circle className="w-6 h-6 text-slate-300" />
                                        )}
                                        {state === 'active' && (
                                            <Loader2 className="w-6 h-6 text-indigo-600 animate-spin" />
                                        )}
                                        {state === 'complete' && (
                                            <CheckCircle2 className="w-6 h-6 text-emerald-500" />
                                        )}
                                        <span
                                            className={`text-sm ${
                                                state === 'pending'
                                                    ? 'text-slate-400'
                                                    : state === 'active'
                                                    ? 'text-slate-900 font-medium'
                                                    : 'text-slate-600 line-through'
                                            }`}
                                        >
                                            {step.label}
                                        </span>
                                    </motion.div>
                                );
                            })}
                        </div>

                        {/* Progress Bar */}
                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden mb-4">
                            <motion.div
                                className="h-full rounded-full animate-shimmer"
                                initial={{ width: 0 }}
                                animate={{ width: `${progress}%` }}
                                transition={{ duration: 0.5, ease: 'easeOut' }}
                            />
                        </div>

                        {/* Time Estimate */}
                        <div className="flex items-center justify-center gap-2 text-sm text-slate-500 mb-6">
                            <Clock className="w-4 h-4" />
                            <span>Usually takes 15-30 seconds</span>
                        </div>

                        {/* Fun Fact */}
                        <motion.div
                            key={currentTip}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -10 }}
                            className="mt-6 p-4 bg-indigo-50 rounded-xl border-l-4 border-indigo-600"
                        >
                            <div className="flex items-start gap-3">
                                <Lightbulb className="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" />
                                <p className="text-sm leading-relaxed text-slate-700">
                                    {funFacts[currentTip]}
                                </p>
                            </div>
                        </motion.div>
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}

