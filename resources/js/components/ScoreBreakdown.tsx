import { motion } from 'framer-motion';
import { FileSearch, Layout, Hash, Mail, Target } from 'lucide-react';
import ProgressBar from './ProgressBar';
import { useCountUp } from '../hooks/useCountUp';

interface ScoreBreakdownProps {
    parseabilityScore?: number;
    formatScore?: number;
    keywordScore?: number;
    contactScore?: number;
    contentScore?: number;
}

interface CategoryData {
    key: string;
    label: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    score?: number;
}

export default function ScoreBreakdown({
    parseabilityScore,
    formatScore,
    keywordScore,
    contactScore,
    contentScore,
}: ScoreBreakdownProps) {
    const categories: CategoryData[] = [
        {
            key: 'parseability',
            label: 'Parseability (Can ATS read it?)',
            description: 'PDF text extraction and document readability',
            icon: FileSearch,
            score: parseabilityScore,
        },
        {
            key: 'format',
            label: 'Format (Standard structure?)',
            description: 'Section headers, document structure, and formatting',
            icon: Layout,
            score: formatScore,
        },
        {
            key: 'keyword',
            label: 'Keywords (Relevant skills?)',
            description: 'Technical skills and industry keyword density',
            icon: Hash,
            score: keywordScore,
        },
        {
            key: 'contact',
            label: 'Contact Info (Easy to find?)',
            description: 'Email, phone, LinkedIn, and location visibility',
            icon: Mail,
            score: contactScore,
        },
        {
            key: 'content',
            label: 'Content Quality (Achievements, verbs?)',
            description: 'Action verbs, metrics, and impact statements',
            icon: Target,
            score: contentScore,
        },
    ].filter((cat) => cat.score !== undefined);

    const getScoreColor = (score: number): string => {
        if (score >= 70) {
            return 'text-emerald-500';
        }
        if (score >= 50) {
            return 'text-amber-500';
        }
        return 'text-rose-500';
    };

    const getIconColor = (score: number): string => {
        if (score >= 70) {
            return 'text-emerald-500';
        }
        if (score >= 50) {
            return 'text-amber-500';
        }
        return 'text-rose-500';
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {categories.map((category, index) => {
                const Icon = category.icon;
                const score = category.score || 0;
                const displayScore = useCountUp(score, 2000);

                return (
                    <motion.div
                        key={category.key}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.4 + index * 0.1, duration: 0.6 }}
                        className="bg-white p-6 rounded-2xl border border-slate-200 hover:shadow-[0_20px_25px_-5px_rgba(0,0,0,0.1),0_10px_10px_-5px_rgba(0,0,0,0.04)] hover:-translate-y-0.5 transition-all duration-300"
                    >
                        {/* Header Row */}
                        <div className="flex justify-between items-center mb-4">
                            <div className="flex items-center gap-3">
                                <Icon className={`w-6 h-6 ${getIconColor(score)}`} />
                                <h3 className="text-base font-semibold text-slate-900">{category.label}</h3>
                            </div>
                            <div className={`text-xl font-bold ${getScoreColor(score)}`}>
                                {displayScore}/100
                            </div>
                        </div>

                        {/* Progress Bar */}
                        <ProgressBar value={score} max={100} delay={500 + index * 100} />

                        {/* Description */}
                        <p className="mt-3 text-sm text-slate-600 leading-relaxed">{category.description}</p>
                    </motion.div>
                );
            })}
        </div>
    );
}
