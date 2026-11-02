import { careerDate } from '../lib/career-date';
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
export type PublicJob = {
    id: number;
    job_title: string;
    department: string;
    location: string;
    employment_type: string;
    type_label: string;
    opening_date: string;
    closing_date: string;
    description?: string;
    requirements?: string;
};
export function CareerJobs({ jobs }: { jobs: PublicJob[] }) {
    return (
        <div className="career-jobs">
            {jobs.map((job) => (
                <Link className="career-job" key={job.id} href={`/karier/${job.id}`}>
                    <div>
                        <h3>{job.job_title}</h3>
                        <p>{job.department}</p>
                    </div>
                    <span>{job.location}</span>
                    <span>
                        {job.type_label}
                        <small>Tutup {careerDate(job.closing_date)}</small>
                    </span>
                    <ArrowUpRight size={20} aria-hidden="true" />
                </Link>
            ))}
        </div>
    );
}
