<?php namespace App\Http\Controllers;

use App\Quiz;
use App\Test;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class QuizController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $user = User::find(Auth::id());
        if ($user->isExaminer()) {
            $quizzes = Quiz::where('user_id', $user->id)
                ->latest()
                ->get();
        } else if ($user->isExaminee()) {
            $quizzes = $user->availableQuizzes()
                ->latest()
                ->get();
        } else {
            $quizzes = Quiz::latest()
                ->get();
        }

        return view('quiz.index', compact('quizzes', 'user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('quiz.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $quiz = $request
            ->user()
            ->quizzes()
            ->create([
                'subject' => $request->get('subject'),
            ]);

        foreach ($request->get('questions') as $q)
        {
            $question = $quiz->questions()->create([
                'text' => $q['text'],
            ]);

            $answers = $q['answers'];

            if ( ! array_key_exists('correct_answer', $q)) {
                $q['correct_answer'] = 0;
            }

            foreach (range(0, 3) as $i)
            {
                $question->answers()->create([
                    'text'        => $answers[$i],
                    'correct'     => ($i == $q['correct_answer']),
                    'question_id' => $question->id,
                ]);
            }
        }

        return redirect()->route('quizzes.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  Quiz  $quiz
     * @return Response
     */
    public function show(Quiz $quiz)
    {
        if (auth()->user()->limitReached($quiz->id)) {
            return Redirect::route('quizzes.index')->with('limit_reached', true);
        }

        $choices = ['A', 'B', 'C', 'D'];

        $all_questions = $quiz->questions()->orderBy('id')->get()->toArray();

        $questions = array_map(
            function($key) use ($quiz) {
                return $quiz->questions[$key];
            },
            (array) array_rand($all_questions, min(10, sizeof($all_questions)))
        );

        return view('quiz.show', compact('quiz', 'questions', 'choices'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Quiz $quiz
     * @return Response
     * @internal param int $id
     */
    public function edit(Quiz $quiz)
    {
        return view('quiz.edit', compact('quiz'));
    }

    /**
     * Update the specified quiz in database
     *
     * @param  Request $request
     * @param Quiz $quiz
     * @return Response
     * @internal param int $id
     */
    public function update(Request $request, Quiz $quiz)
    {
        $quiz->update([
            'subject' => $request->get('subject'),
        ]);

        foreach ($quiz->questions as $question)
        {
            $q = $request->get('existingquestions')[$question->id];

            $question->text = $q['text'];
            $question->save();

            foreach ($question->answers as $answer)
            {
                $answer->text    = $q['answers'][$answer->id];
                $answer->correct = ($q['correct_answer'] == $answer->id);
                $answer->save();
            }
        }

        foreach ($request->get('questions') as $q)
        {
            $question = $quiz->questions()->create([
                'text' => $q['text'],
            ]);

            $answers = $q['answers'];

            if ( ! array_key_exists('correct_answer', $q)) {
                $q['correct_answer'] = 0;
            }

            foreach (range(0, 3) as $i)
            {
                $question->answers()->create([
                    'text'        => $answers[$i],
                    'correct'     => ($i == $q['correct_answer']),
                    'question_id' => $question->id,
                ]);
            }
        }

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Quiz $quiz
     * @return Response
     * @throws \Exception
     * @internal param int $id
     */
    public function destroy(Quiz $quiz)
    {
        foreach ($quiz->tests as $test)
        {
            foreach ($test->results as $result)
            {
                $result->delete();
            }

            $test->delete();
        }

        foreach ($quiz->questions as $question)
        {
            $question->answers()->delete();
        }

        $quiz->questions()->delete();
        $quiz->delete();

        return redirect()->back();
    }

    public function examinees(Quiz $quiz)
    {
        $examinees = User::whereType('examinee')->get();

        return view('quiz.examinees', compact('quiz', 'examinees'));
    }

    public function addExaminee(Quiz $quiz, Request $request)
    {
        $quiz->examinees()->detach();

        foreach ($request->get('examinees', []) as $id)
        {
            $quiz->examinees()->attach($id);
        }

        return redirect()->back();
    }
}
