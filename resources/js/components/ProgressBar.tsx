import { useEffect, useState } from 'react';

interface ProgressBarProps {
    value: number;
    max: number;
    label?: string;
    color?: 'green' | 'yellow' | 'red' | 'blue';
    delay?: number;
}

export default function ProgressBar({ value, max, label, color = 'blue', delay = 0 }: ProgressBarProps) {
    const safeValue = Math.max(0, value);
    const safeMax = Math.max(1, max);
    const percentage = Math.min((safeValue / safeMax) * 100, 100);
    const [animatedWidth, setAnimatedWidth] = useState(0);

    useEffect(() => {
        const timer = setTimeout(() => {
            setAnimatedWidth(percentage);
        }, delay);

        return () => clearTimeout(timer);
    }, [percentage, delay]);

    const getGradientClass = (): string => {
        if (percentage >= 70) {
            return 'bg-gradient-to-r from-emerald-500 to-emerald-600';
        }
        if (percentage >= 50) {
            return 'bg-gradient-to-r from-amber-500 to-amber-600';
        }
        return 'bg-gradient-to-r from-rose-500 to-rose-600';
    };

    return (
        <div className="w-full">
            {label && (
                <div className="mb-2 flex justify-between text-sm">
                    <span className="font-medium text-slate-700">{label}</span>
                    <span className="text-slate-600">
                        {value} / {max}
                    </span>
                </div>
            )}
            <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    className={`h-full rounded-full transition-all duration-1000 ease-out ${getGradientClass()}`}
                    style={{ width: `${animatedWidth}%` }}
                />
            </div>
        </div>
    );
}

