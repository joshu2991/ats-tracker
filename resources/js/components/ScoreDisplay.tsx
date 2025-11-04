import { useEffect, useState } from 'react';

interface ScoreDisplayProps {
    score: number;
    maxScore?: number;
}

export default function ScoreDisplay({ score, maxScore = 100 }: ScoreDisplayProps) {
    const [displayScore, setDisplayScore] = useState(0);

    useEffect(() => {
        // Animate counter from 0 to score
        const duration = 1500; // 1.5 seconds
        const steps = 60;
        const increment = score / steps;
        const stepDuration = duration / steps;

        let currentStep = 0;
        const timer = setInterval(() => {
            currentStep++;
            const nextValue = Math.min(Math.round(increment * currentStep), score);
            setDisplayScore(nextValue);

            if (currentStep >= steps || nextValue >= score) {
                setDisplayScore(score);
                clearInterval(timer);
            }
        }, stepDuration);

        return () => clearInterval(timer);
    }, [score]);

    const getScoreColor = (): string => {
        const percentage = (score / maxScore) * 100;
        if (percentage >= 80) {
            return 'text-green-600 dark:text-green-400';
        }
        if (percentage >= 60) {
            return 'text-yellow-600 dark:text-yellow-400';
        }
        return 'text-red-600 dark:text-red-400';
    };

    const getScoreLabel = (): string => {
        const percentage = (score / maxScore) * 100;
        if (percentage >= 80) {
            return 'Excellent';
        }
        if (percentage >= 60) {
            return 'Good';
        }
        return 'Needs Improvement';
    };

    return (
        <div className="text-center">
            <div className={`text-6xl font-bold ${getScoreColor()}`}>
                {displayScore}
                <span className="text-3xl text-gray-500 dark:text-gray-400">/{maxScore}</span>
            </div>
            <div className={`mt-2 text-xl font-semibold ${getScoreColor()}`}>{getScoreLabel()}</div>
        </div>
    );
}

