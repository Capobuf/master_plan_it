import PageMeta from "../../components/common/PageMeta";
import AuthLayout from "./AuthPageLayout";
import SignInForm from "../../components/auth/SignInForm";

export default function SignIn() {
  return (
    <>
      <PageMeta
        title="Sign in | Master Plan IT"
        description="Sign in to the Master Plan IT application"
      />
      <AuthLayout>
        <SignInForm />
      </AuthLayout>
    </>
  );
}
