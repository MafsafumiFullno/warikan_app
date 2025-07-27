import { useState } from "react";
import Link from "next/link";

export default function ProjectsList() {
    const [projects, setProjects] = useState([
        { id: 1, name: "Project A" },
        { id: 2, name: "Project B" },
        { id: 3, name: "Project C" },
    ]);
    
    return (
        <div>
            <h1>プロジェクト一覧</h1>
            <ul>
                {projects.map((project) => (
                <li key={project.id}>
                    <Link href={`/projects/${project.id}`}>
                    {project.name}
                    </Link>
                </li>
                ))}
            </ul>
        </div>
    );
}