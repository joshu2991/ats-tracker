import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Brain, Circle, Loader2, CheckCircle2, Clock, Lightbulb } from 'lucide-react';

interface AnalysisLoadingModalProps {
    isOpen: boolean;
}

const steps = [
    { id: 1, label: 'Extracting text from PDF', duration: 3500, minDisplayTime: 2000 },
    { id: 2, label: 'Analyzing format and structure', duration: 4000, minDisplayTime: 2500 },
    { id: 3, label: 'Checking keyword density', duration: 3500, minDisplayTime: 2000 },
    { id: 4, label: 'Running AI analysis', duration: 8000, minDisplayTime: 4000 },
    { id: 5, label: 'Generating personalized suggestions', duration: 3500, minDisplayTime: 2000 },
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
    const [completedSteps, setCompletedSteps] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (!isOpen) {
            setCurrentStep(0);
            setProgress(0);
            setCompletedSteps(new Set());
            return;
        }

        let accumulatedTime = 0;
        const totalDuration = steps.reduce((sum, step) => sum + step.duration, 0);
        const stepTransitionDelay = 1000; // Delay between steps for smoother transition
        const progressUpdateInterval = 50; // Update progress every 50ms for smooth animation

        // Update progress smoothly
        const progressInterval = setInterval(() => {
            setProgress((prev) => {
                if (prev < 100) {
                    return Math.min(prev + 0.3, 100);
                }
                return prev;
            });
        }, progressUpdateInterval);

        steps.forEach((step, index) => {
            // Start the step (show as active)
            setTimeout(() => {
                setCurrentStep(index + 1);
            }, accumulatedTime);

            // Complete the step (show checkmark) after duration
            setTimeout(() => {
                setCompletedSteps((prev) => new Set([...prev, index]));
                
                // Small delay before moving to next step for better UX
                setTimeout(() => {
                    if (index < steps.length - 1) {
                        setCurrentStep(index + 2);
                    }
                }, stepTransitionDelay);
            }, accumulatedTime + step.duration);

            accumulatedTime += step.duration + stepTransitionDelay;
        });

        // Clear progress interval when all steps complete
        const totalTime = accumulatedTime;
        setTimeout(() => {
            clearInterval(progressInterval);
            setProgress(100);
        }, totalTime);

        // Rotate tips every 5 seconds
        const tipInterval = setInterval(() => {
            setCurrentTip((prev) => (prev + 1) % funFacts.length);
        }, 5000);

        return () => {
            clearInterval(tipInterval);
            clearInterval(progressInterval);
        };
    }, [isOpen]);

    const getStepState = (stepIndex: number): 'pending' | 'active' | 'complete' => {
        if (completedSteps.has(stepIndex)) {
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
                                const isVisible = state !== 'pending' || index < currentStep + 1;
                                
                                return (
                                    <AnimatePresence key={step.id}>
                                        {isVisible && (
                                            <motion.div
                                                initial={{ opacity: 0, x: -20 }}
                                                animate={{ 
                                                    opacity: 1, 
                                                    x: 0,
                                                    scale: state === 'active' ? 1.02 : 1,
                                                }}
                                                exit={state === 'complete' ? { 
                                                    opacity: 0.5,
                                                    x: 10,
                                                    transition: { duration: 0.6, delay: 0.3 }
                                                } : {}}
                                                transition={{ 
                                                    duration: 0.5,
                                                    ease: 'easeOut',
                                                    scale: { duration: 0.3 }
                                                }}
                                                className={`flex items-center gap-4 transition-all duration-500 ${
                                                    state === 'active' ? 'transform' : ''
                                                }`}
                                            >
                                                {/* Icon with smooth transitions */}
                                                <motion.div
                                                    key={`icon-${state}-${index}`}
                                                    initial={{ scale: 0.8, opacity: 0 }}
                                                    animate={{ scale: 1, opacity: 1 }}
                                                    transition={{ duration: 0.4, ease: 'easeOut' }}
                                                    className="flex-shrink-0"
                                                >
                                                    {state === 'pending' && (
                                                        <Circle className="w-6 h-6 text-slate-300" />
                                                    )}
                                                    {state === 'active' && (
                                                        <Loader2 className="w-6 h-6 text-indigo-600 animate-spin" />
                                                    )}
                                                    {state === 'complete' && (
                                                        <motion.div
                                                            initial={{ scale: 0, rotate: -180 }}
                                                            animate={{ scale: 1, rotate: 0 }}
                                                            transition={{ 
                                                                type: 'spring',
                                                                stiffness: 200,
                                                                damping: 15,
                                                                duration: 0.6
                                                            }}
                                                        >
                                                            <CheckCircle2 className="w-6 h-6 text-emerald-500" />
                                                        </motion.div>
                                                    )}
                                                </motion.div>
                                                
                                                {/* Text with smooth state changes */}
                                                <motion.span
                                                    key={`text-${state}-${index}`}
                                                    animate={{
                                                        color: state === 'pending' 
                                                            ? '#94a3b8' 
                                                            : state === 'active' 
                                                            ? '#0f172a' 
                                                            : '#64748b',
                                                        textDecoration: state === 'complete' ? 'line-through' : 'none',
                                                        opacity: state === 'complete' ? 0.7 : 1,
                                                    }}
                                                    transition={{ duration: 0.5, ease: 'easeInOut' }}
                                                    className={`text-sm ${
                                                        state === 'active' ? 'font-semibold' : 'font-normal'
                                                    }`}
                                                >
                                                    {step.label}
                                                </motion.span>
                                            </motion.div>
                                        )}
                                    </AnimatePresence>
                                );
                            })}
                        </div>

                        {/* Progress Bar */}
                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden mb-4">
                            <motion.div
                                className="h-full rounded-full bg-gradient-to-r from-indigo-500 via-indigo-600 to-indigo-700 animate-shimmer"
                                initial={{ width: 0 }}
                                animate={{ width: `${Math.min(progress, 100)}%` }}
                                transition={{ duration: 0.8, ease: 'easeOut' }}
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

