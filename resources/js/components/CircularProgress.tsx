import { useId } from 'react';
import { useCountUp } from '../hooks/useCountUp';

interface CircularProgressProps {
    score: number;
    size?: number;
    strokeWidth?: number;
    className?: string;
}

export default function CircularProgress({ score, size = 200, strokeWidth = 12, className = '' }: CircularProgressProps) {
    // Generate unique IDs for gradients to avoid conflicts when multiple rings are on the same page
    const uniqueId = useId();
    const gradientGreenId = `gradient-green-${uniqueId}`;
    const gradientAmberId = `gradient-amber-${uniqueId}`;
    const gradientRoseId = `gradient-rose-${uniqueId}`;

    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const progress = useCountUp(score, 2000);
    const offset = circumference - (progress / 100) * circumference;

    const getColor = (score: number): string => {
        if (score >= 70) {
            return `url(#${gradientGreenId})`;
        }
        if (score >= 50) {
            return `url(#${gradientAmberId})`;
        }
        return `url(#${gradientRoseId})`;
    };

    return (
        <svg width={size} height={size} className={`transform -rotate-90 ${className}`}>
            <defs>
                <linearGradient id={gradientGreenId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#10B981" />
                    <stop offset="100%" stopColor="#059669" />
                </linearGradient>
                <linearGradient id={gradientAmberId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#F59E0B" />
                    <stop offset="100%" stopColor="#D97706" />
                </linearGradient>
                <linearGradient id={gradientRoseId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#F43F5E" />
                    <stop offset="100%" stopColor="#E11D48" />
                </linearGradient>
            </defs>

            {/* Background circle */}
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke="#F1F5F9"
                strokeWidth={strokeWidth}
            />

            {/* Progress circle */}
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke={getColor(score)}
                strokeWidth={strokeWidth}
                strokeDasharray={circumference}
                strokeDashoffset={offset}
                strokeLinecap="round"
                style={{
                    transition: 'stroke-dashoffset 2s cubic-bezier(0.4, 0, 0.2, 1)',
                }}
            />
        </svg>
    );
}

