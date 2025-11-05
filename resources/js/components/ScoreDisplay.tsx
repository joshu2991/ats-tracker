import { useCountUp } from '../hooks/useCountUp';
import CircularProgress from './CircularProgress';

interface ScoreDisplayProps {
    score: number;
    maxScore?: number;
}

export default function ScoreDisplay({ score, maxScore = 100 }: ScoreDisplayProps) {
    const displayScore = useCountUp(score, 2000);

    const getGradientClass = (): string => {
        if (score >= 70) {
            return 'bg-gradient-to-r from-emerald-500 to-emerald-600';
        }
        if (score >= 50) {
            return 'bg-gradient-to-r from-amber-500 to-amber-600';
        }
        return 'bg-gradient-to-r from-rose-500 to-rose-600';
    };

    const getBadgeClass = (): string => {
        if (score >= 70) {
            return 'bg-emerald-100 text-emerald-700';
        }
        if (score >= 50) {
            return 'bg-amber-100 text-amber-700';
        }
        return 'bg-rose-100 text-rose-700';
    };

    const getScoreLabel = (): string => {
        if (score >= 70) {
            return 'Excellent';
        }
        if (score >= 50) {
            return 'Good';
        }
        return 'Needs Improvement';
    };

    return (
        <div className="flex flex-col items-center my-8">
            {/* Circular Progress Ring */}
            <div className="relative w-[200px] h-[200px] flex items-center justify-center">
                <CircularProgress score={score} size={200} strokeWidth={12} />

                {/* Score Number */}
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <span
                        className={`text-6xl font-bold bg-clip-text text-transparent ${getGradientClass()}`}
                    >
                        {displayScore}
                    </span>
                    <span className="text-2xl text-slate-400 relative -top-2">/100</span>
                </div>
            </div>

            {/* Score Label Badge */}
            <div className={`mt-6 px-5 py-2 rounded-full text-base font-semibold ${getBadgeClass()}`}>
                {getScoreLabel()}
            </div>
        </div>
    );
}

