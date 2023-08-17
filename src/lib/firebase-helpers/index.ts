import {app, database} from './firebase-config';
import { getDatabase, get, child, ref, push } from "firebase/database";
import { Blog, Profile, Project, Session, Tag } from '@/types/types';
import { ResponseParser } from '../response-parser';

const dbRef = ref(database);
const sync = (entity: string) => get(child(dbRef, `${entity}/`));

export class FirebaseHelper {
    static async syncAllProjects(): Promise<Project[]> {
        const snapshot = await sync('projects');
        let projects = ResponseParser.parse<Project>(snapshot.val());
        await this.attachTags(projects) as unknown as Project[];
        return projects.sort((a, b) => (a.pos || 0) - (b.pos || 0))
    }

    static async syncAllBlogs(): Promise<Blog[]> {
        const snapshot = await sync('blogs');
        const blogs = ResponseParser.parse<Blog>(snapshot.val());
        await this.attachTags(blogs);
        return blogs.sort((a, b) => (a.pos || 0) - (b.pos || 0))
    }

    static async syncAllTags(): Promise<Tag[]> {
        const snapshot = await sync('tags');
        const tags = ResponseParser.parse<Tag>(snapshot.val());
        return tags;
    }

    static async syncMyProfile(): Promise<Profile> {
        const snapshot = await sync('profile');
        const myProfile = snapshot.val() as Profile;
        myProfile.companies = myProfile.companies.reverse();
        return myProfile;
    }

    static async syncAllSessions(): Promise<Session[]> {
        const snapshot = await sync('sessions');
        const sessions = ResponseParser.parse<Session>(snapshot.val());
        await this.attachTags(sessions);
        return sessions;
    }

    static async attachTags(entity: Project[] | Blog[] | Session[]) {
        const tags = await this.syncAllTags();
        entity.forEach((e) => {
            e.tags = (e.tags || []).map((x) => {
                return tags.find(t => t.id === x)
            }) as any
        })
        return entity
    }

    static sendMessage = (message: {name: string; email: string; message: string}) => {
        push(ref(database, 'contact/'), {
            ...message,
            seen: false,
            createdAt: new Date().toISOString()
        })
      };
}