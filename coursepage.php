<?php
include 'conn.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['courseId']) || empty($_POST['courseId'])) {
        exit();
    }

    $courseId = intval($_POST['courseId']);

    // If the session exists and the course_id is different, destroy the session
    if (!isset($_SESSION['course_id']) || $_SESSION['course_id'] !== $courseId) {
        session_unset();
        session_destroy();
        session_start();
       
    }
    

    // Prepare the SQL query to fetch the course details
    $query = "SELECT * FROM courses WHERE courseId = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Course not found.");
    }

    $course = $result->fetch_assoc();

    // Extract the relevant details from the $course array
    $courseName = htmlspecialchars($course['courseName']);
    $instructorEmail = htmlspecialchars($course['instructor_email']);
    $thumbnail = htmlspecialchars($course['thumbnail']);
    $courseRatings = floatval($course['course_ratings']); 

    // Define the base URL path to the thumbnails
    $thumbnailBaseUrl = "/UploadCourse/Course-Thumbnails";
    $thumbnailPath = "$thumbnailBaseUrl/$instructorEmail/$courseName/$thumbnail";

    // Fetch instructor name
    $instructorQuery = "SELECT instructor_name FROM instructor WHERE email = ?";
    $instructorStmt = $conn->prepare($instructorQuery);

    if ($instructorStmt === false) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $instructorStmt->bind_param('s', $instructorEmail);
    $instructorStmt->execute();
    $instructorResult = $instructorStmt->get_result();

    if ($instructorResult->num_rows === 0) {
        $instructorName = "Unknown Instructor";
    } else {
        $instructorRow = $instructorResult->fetch_assoc();
        $instructorName = htmlspecialchars($instructorRow['instructor_name']);
    }

    // Assign session variables
    $_SESSION['learner_id'] = isset($_SESSION['learner_id']) ? $_SESSION['learner_id'] : 1; // Default or fetched learner ID
    $_SESSION['course_id'] = $courseId;

} else {
    die("Invalid request method.");
}


?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="coursepage.css">

<div class="container">
    <div class="thum">
        <img id="thumbnail" src="<?= $thumbnailPath ?>" alt="Course Thumbnail">
    </div>
    <div class="des">
    <div class="course-header">
    <h1 id="courseName" class="course-name"><?= $courseName ?></h1>
    <div class="star-ratings-container">
        <div class="course-rating-number">
            <span id="rating-number"><?= number_format($courseRatings, 1) ?></span>
        </div>
                    <div class="star-ratings" id="star-rating">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            $starType = $i <= $course['course_ratings'] ? 'full' : ($i === ceil($course['course_ratings']) ? 'half' : 'empty');
                            echo "<label class='star-label $starType'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='clamp(1.8rem, 4vw, 1.8rem)' height='clamp(2rem, 4vw, 2rem)' viewBox='0 0 24 24' fill='" . ($starType === 'full' ? '#ffb633' : '#e0e0e0') . "'>
                                        <path d='M12 2.3l2.4 7.4h7.6l-6 4.8 2.3 7.4-6.3-4.7-6.3 4.7 2.3-7.4-6-4.8h7.6z'/>
                                    </svg>
                                </label>";
                        }
                        ?>
                    </div>
                </div>
            </div>
                    <h2 id="instructorName" class="instructor-name">Instructor: <?= $instructorName ?></h2>
        <h3 id="difficulty" class="lig">Difficulty: <?= htmlspecialchars($course['difficulty']) ?></h3>
        <h3 id="lessons" class="lig">Lessons: <?= htmlspecialchars($course['lessons']) ?></h3>
        <div class="description">
            <h3>Description :</h3>
            <p id="description"><?= htmlspecialchars($course['description']) ?></p>
        </div>
        <div>
            <button id="default-enroll-button" class="enroll-button" data-course-id="<?= $courseId ?>">
                Enroll Now
            </button>
        </div>
    </div>
    <div class="com">
        <input type="hidden" id="courseId" value="<?= $courseId ?>">
    </div>
</div>

<div class="course-box">
    <div class="arrow left" onclick="scrollCourses(-1)">&#10094;</div>
    <div class="courses-container" id="courses-container"></div>
    <div class="arrow right" onclick="scrollCourses(1)">&#10095;</div>
</div>
<script src="node_modules/jquery/dist/jquery.min.js"></script>


    <script>

function showReplies() {
    const repliesContainer = document.querySelector('.replies-container');
    
    // Expand the container
    repliesContainer.style.maxHeight = repliesContainer.scrollHeight + "px";
    
    // Scroll to the top after expanding
    setTimeout(() => {
        repliesContainer.scrollTo(0, 0); // Scroll to the top position
    }, 300); // Match this timeout with the CSS transition timing
}



function scrollCourses(direction) {
    const container = document.getElementById('courses-container');
    const items = container.children;
    
    // Calculate the width of one item plus any gaps
    const itemWidth = items[0].offsetWidth;
    const gapWidth = parseFloat(getComputedStyle(container).gap);
    const scrollAmount = (itemWidth + gapWidth);
    const scrollItems = Math.floor(container.offsetWidth / scrollAmount);

    if (direction === -1) {
        container.scrollLeft -= scrollAmount * scrollItems;
    } else if (direction === 1) {
        container.scrollLeft += scrollAmount * scrollItems;
    }
}   

function loadCourses() {
        $.ajax({
            url: 'fetch_homepage.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    $('#courses-container').html('<p>' + response.error + '</p>');
                } else {
                    $('#courses-container').empty();
                    response.forEach(course => {
                        const courseContainer = $('<div>').addClass('course-con');
                        const courseDiv = $('<div>').addClass('folder').attr('data-course-id', course.courseId);

                        const courseRatings = course.course_ratings;
                        let ratingHtml = '<div class="star-rating">';
                        for (let i = 1; i <= 5; i++) {
                            let starType = 'empty';
                            if (i <= courseRatings) {
                                starType = 'full';
                            } else if (i === Math.floor(courseRatings) + 1 && courseRatings % 1 !== 0) {
                                starType = 'half';
                            }
                            
                            ratingHtml += `
                                <input type="radio" id="star${i}" name="rating" value="${i}" class="star-input" style="display: none;">
                                <label for="star${i}" class="star-label ${starType}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="clamp(1rem, 2vw, 1.75rem)" height="clamp(1rem, 2vw, 1.75rem)" viewBox="0 0 24 24" fill="${starType === 'full' ? '#ffb633' : '#e0e0e0'}">
                                        <path d="M12 2.3l2.4 7.4h7.6l-6 4.8 2.3 7.4-6.3-4.7-6.3 4.7 2.3-7.4-6-4.8h7.6z"/>
                                    </svg>
                                </label>`;
                        }
                        ratingHtml += '</div>';

                        courseDiv.html(`
                            <img src="${course.thumbnail}" alt="${course.coursename}" class="course-thumbnail">
                            <h3 class="cn">${course.coursename}</h3>
                            <p>${course.difficulty}</p>
                            ${ratingHtml}
                           
                        `);

                        courseDiv.click(function() {
                        const courseId = $(this).data('course-id');
                        if (courseId) {
                            console.log('Course ID from index page:', courseId);
                            loadCourseDetails(courseId);
                            loadCourseComments(courseId);
                            $('#courseId').val(courseId);
                            $('#courseForm').attr('action', 'coursepage.php').submit(); 
                            $('#default-enroll-button').data('course-id', courseId);
                        } else {
                            alert("Course ID is not defined.");
                        }
                    });

                        courseContainer.append(courseDiv);
                        $('#courses-container').append(courseContainer);
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#courses-container').html('<p>There was an error fetching the courses.</p>');
                console.error('Error fetching courses:', error);
            }
        });
    }

     // Handle enroll button clicks
     $(document).on('click', '.enroll-button', function() {
        const courseId = $(this).data('course-id');
        console.log("Clicked Enroll Button Course ID:", courseId);

        fetch('', {
            method: 'POST',
            body: JSON.stringify({ courseId: courseId }),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.text())
        .then(data => {
            console.log(data);
        })
        .catch(error => console.error('Error:', error));
    });

    // Function to load course details
    function loadCourseDetails(courseId) {
    $.ajax({
        url: 'reccourse.php',
        type: 'POST',
        data: { courseId: courseId },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                alert(response.error);
                return;
            }

            // Update course details
            $('#courseName').text(response.courseName || 'Course Name');
            $('#instructorName').text('Instructor: ' + (response.instructorName || 'N/A'));
            $('#difficulty').text('Difficulty: ' + (response.difficulty || 'N/A'));
            $('#lessons').text('Lessons: ' + (response.lessons || 'N/A'));
            $('#description').text(response.description || 'No description available.');
            $('#thumbnail').attr('src', response.thumbnail || 'default-thumbnail.png').attr('alt', response.courseName || 'Course Thumbnail');

            // Update course rating number
            const courseRatings = response.courseRatings || 0;
            $('#rating-number').text(courseRatings.toFixed(1)); // Update the rating number

            // Update star ratings
            let ratingHtml = '<div class="star-rating">';
            for (let i = 1; i <= 5; i++) {
                let starType = 'empty';
                if (i <= courseRatings) {
                    starType = 'full';
                } else if (i === Math.floor(courseRatings) + 1 && courseRatings % 1 !== 0) {
                    starType = 'half';
                }

                ratingHtml += `
                    <input type="radio" id="star${i}" name="rating" value="${i}" class="star-input" style="display: none;">
                    <label for="star${i}" class="star-label ${starType}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="clamp(2rem, 4vw, 2rem)" height="clamp(2rem, 4vw, 2rem)" viewBox="0 0 24 24" fill="${starType === 'full' ? '#ffb633' : '#e0e0e0'}">
                            <path d="M12 2.3l2.4 7.4h7.6l-6 4.8 2.3 7.4-6.3-4.7-6.3 4.7 2.3-7.4-6-4.8h7.6z"/>
                        </svg>
                    </label>`;
            }
            ratingHtml += '</div>';
            $('#star-rating').html(ratingHtml);
        },
        error: function(xhr, status, error) {
            alert('There was an error loading the course details.');
            console.error('Error loading course details:', error);
        }
    });
}

$(document).ready(function() {
    // Initial call to load courses
    loadCourses();
});






$(document).ready(function() {
    // Get the course ID from the hidden input field
    var courseId = $('#courseId').val();
    
    // Call loadCourseComments if courseId is not null
    if (courseId) {
        loadCourseComments(courseId);
    }
});

// Load course comments
function loadCourseComments(course_id) {
    $.ajax({
        url: 'fetch_reviews.php',
        type: 'POST',
        data: { course_id: course_id },
        dataType: 'json',
        success: function(response) {
            var commentsDiv = $('.com');
            commentsDiv.empty();

            if (response.length > 0) {
                $.each(response, function(index, comment) {
                    var commentHTML = '<div class="comment-item">';
                    commentHTML += '<div class="comment-header">';
                    /*commentHTML += '<img src="path-to-avatar" alt="avatar" class="comment-avatar">'; */
                    commentHTML += '<div class="comment-author-container">';
                    commentHTML += '<p class="comment-author">' + comment.learner_fullname + '</p>';
                    commentHTML += '<p class="comment-time">3 hours ago</p>'; // Placeholder for time
                    commentHTML += '</div>';
                    commentHTML += '</div>';

                    commentHTML += '<p class="comment-text">' + comment.review + '</p>';

                    commentHTML += '<div class="comment-actions">';
                    // Like Button
                    commentHTML += `
                        <button class="like-button">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="rgba(0, 0, 0, 1)">
                                <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                            </svg>
                        </button>`;

                    // Dislike Button
                    commentHTML += `
                        <button class="dislike-button">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="rgba(0, 0, 0, 1)">
                                <path d="M20 3h-1v13h1a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM4 16h7l-1.122 3.368A2 2 0 0 0 11.775 22H12l5-5.438V3H6l-3.937 8.649-.063.293V14a2 2 0 0 0 2 2z"/>
                            </svg>
                        </button>`;
                    commentHTML += '<button class="reply-button" data-comment-id="' + comment.course_reviews_id + '">Reply</button>';
                    commentHTML += '</div>'; // Close comment-actions

                    // Add the "See Replies" button and replies container below the comment-actions
                    if (comment.replies && comment.replies.length > 0) {
                        commentHTML += '<button class="see-replies-button">See Replies (' + comment.replies.length + ')</button>';
                        commentHTML += '<div class="replies-container">';
                        $.each(comment.replies, function(replyIndex, reply) {
                            commentHTML += '<div class="reply-item">';
                            commentHTML += '<div class="reply-header">';
                            commentHTML += '<div class="reply-author-container">';
                            commentHTML += '<p class="reply-author">You</p>';
                            commentHTML += '<p class="reply-time">Just now</p>'; // Placeholder for reply time
                            commentHTML += '</div>';
                            commentHTML += '</div>';
                            commentHTML += '<p class="reply-text">' + reply + '</p>'; // Assuming `reply` has `reply_review`
                            commentHTML += '</div>';
                        });
                        commentHTML += '</div>'; // Close replies-container
                    }

                    commentHTML += '</div>'; // Close comment-item
                    commentsDiv.append(commentHTML);
                });

                // Event delegation to handle "See Replies" button click
                commentsDiv.off('click', '.see-replies-button').on('click', '.see-replies-button', function() {
                    var $button = $(this);
                    var $repliesContainer = $button.siblings('.replies-container');
                    
                    if ($repliesContainer.hasClass('show')) {
                        $repliesContainer.removeClass('show');
                        $button.text('See Replies (' + $repliesContainer.find('.reply-item').length + ')');
                    } else {
                        $repliesContainer.addClass('show');
                        $button.text('Hide Replies');
                    }
                });

            } else {
                commentsDiv.append('<p>No comments available for this course.</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
        }
    });
}


$(document).on('click', '.reply-button', function() {
    var commentId = $(this).data('comment-id');
    var commentItem = $(this).closest('.comment-item');
    
    // Hide all other reply forms
    $('.reply-form').remove();
    
    // Check if a reply form already exists within this specific comment-item
    if (commentItem.find('.reply-form').length === 0) {
        // Insert the reply form directly above the replies container
        commentItem.find('.replies-container').before(`
            <div class="reply-form">
                <textarea placeholder="Write a reply..." required></textarea>
                <div class="reply-buttons">
                    <button class="cancel-reply">Cancel</button>
                    <button class="submit-reply" data-comment-id="${commentId}">Submit Reply</button>
                </div>
            </div>
        `);
    }
});
// Cancel reply and remove the reply form
$(document).on('click', '.cancel-reply', function() {
    $(this).closest('.reply-form').remove();
});

// AJAX to submit reply
$(document).on('click', '.submit-reply', function() {
    var $button = $(this);
    var commentId = $button.data('comment-id');
    var $replyForm = $button.closest('.reply-form');
    var replyText = $replyForm.find('textarea').val();
    
    // Ensure both values are present
    if (commentId && replyText) {
        $.ajax({
            url: 'submit_reply.php',
            method: 'POST',
            data: {
                comment_id: commentId,
                reply: replyText
            },
            success: function(response) {
                var newReplyHTML = '<div class="reply-item">' +
                                   '<div class="reply-header">' +
                                   '<p class="reply-author">You</p>' +
                                   '<p class="reply-time">Just now</p>' + // Placeholder for reply time
                                   '</div>' +
                                   '<p class="reply-text">' + replyText + '</p>' +
                                   '</div>';

                // Append the new reply to the replies container within the current comment-item
                $button.closest('.comment-item').find('.replies-container').append(newReplyHTML);

                // Remove the reply form after submission
                $replyForm.remove();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                alert('Failed to submit reply.');
            }
        });
    } else {
        alert('Comment ID or reply text is missing.');
    }
});


    
    </script>
	